<?php

namespace App\Servicios\Monitoreo;

use App\Models\Aplicacion;
use App\Models\EstadoApp;
use App\Models\EstadoSistema;
use Carbon\CarbonImmutable;

/**
 * Toma una lectura de cada app y del sistema, y la guarda (comando consola:recolectar).
 */
class Recolector
{
    public function __construct(
        private Systemd $systemd,
        private ProbadorSalud $salud,
        private LecturaApps $lectura,
        private LectorLog $logs,
        private EvaluadorApp $evaluador,
        private LectorSistema $sistema,
    ) {}

    public function recolectarApp(Aplicacion $app): EstadoApp
    {
        $datos = $this->leerApp($app);
        $datos += $this->evaluador->evaluar($app, $datos);

        return $app->estados()->create($datos + ['created_at' => now()]);
    }

    /** @return array<string, mixed> */
    public function leerApp(Aplicacion $app): array
    {
        $servicio = $app->esOctane() && $app->servicio
            ? $this->systemd->propiedades($app->servicio)
            : $this->systemd->propiedades(config('consola.servicio_fpm'));

        $sesiones = $this->lectura->conteoSesiones($app);

        return [
            'servicio_estado' => $servicio['estado'],
            'servicio_subestado' => $servicio['subestado'],
            'cola_estado' => $app->esOctane() && $app->servicio_cola ? $this->systemd->estado($app->servicio_cola) : null,
            'timer_estado' => $app->esOctane() && $app->timer_scheduler ? $this->systemd->estado($app->timer_scheduler) : null,
            'pid' => $app->esOctane() ? $servicio['pid'] : null,
            'memoria_bytes' => $app->esOctane() ? $servicio['memoria'] : null,
            'cpu_ns' => $app->esOctane() ? $servicio['cpu_ns'] : null,
            'activo_desde' => $servicio['desde'],
            'reinicios_systemd' => $servicio['reinicios'],
            'reinicios_24h' => $this->reinicios24h($app, $servicio['desde']),
            ...$this->mapearSalud($this->salud->probar($app->url_salud)),
            'usuarios_conectados' => $sesiones['usuarios'] ?? null,
            'sesiones_invitado' => $sesiones['invitados'] ?? null,
            'jobs_pendientes' => $this->lectura->jobsPendientes($app),
            'jobs_fallidos_24h' => $this->lectura->fallidos24h($app),
            'errores_log_24h' => $this->logs->erroresUltimas24h($app),
        ];
    }

    public function recolectarSistema(): EstadoSistema
    {
        $datos = $this->sistema->leer();
        $datos += $this->sistema->evaluar($datos);

        return EstadoSistema::create($datos + ['created_at' => now()]);
    }

    /** Borra lecturas más viejas que consola.dias_historial. */
    public function purgar(): int
    {
        $limite = now()->subDays((int) config('consola.dias_historial', 7));

        return EstadoApp::where('created_at', '<', $limite)->delete()
            + EstadoSistema::where('created_at', '<', $limite)->delete();
    }

    /**
     * Arranques en las últimas 24 h = cambios de ActiveEnterTimestamp registrados en ese periodo.
     */
    private function reinicios24h(Aplicacion $app, ?CarbonImmutable $desdeActual): ?int
    {
        if ($desdeActual === null) {
            return null;
        }

        $hace24h = now()->subDay();
        $arranques = $app->estados()
            ->where('created_at', '>=', $hace24h)
            ->whereNotNull('activo_desde')
            ->distinct()
            ->pluck('activo_desde')
            ->map(fn ($f) => CarbonImmutable::parse($f)->getTimestamp())
            ->push($desdeActual->getTimestamp())
            ->unique()
            ->filter(fn ($t) => $t >= $hace24h->getTimestamp());

        // Un arranque dentro de las 24 h cuenta como reinicio solo si ya teníamos historial previo
        $hayHistorial = $app->estados()->where('created_at', '<', now()->subMinutes(5))->exists();

        return $hayHistorial ? $arranques->count() : 0;
    }

    private function mapearSalud(array $salud): array
    {
        return [
            'http_codigo' => $salud['codigo'],
            'latencia_ms' => $salud['latencia_ms'],
            'http_error' => $salud['error'],
        ];
    }
}
