<?php

namespace App\Servicios\Respaldos;

use App\Models\Aplicacion;
use App\Models\Respaldo;
use App\Servicios\Control\ConsolaCtl;
use Carbon\CarbonImmutable;

/**
 * Paso 14: respaldos diarios de las bases de las apps activas.
 * El volcado lo hace root con consola-ctl; aquí se decide qué respaldar, se registra y se purga.
 */
class ServicioRespaldos
{
    /** Salida esperada de consola-ctl: OK archivo=/var/backups/consola/x/x-20261112-023000.sql.gz bytes=123 sha256=abc… */
    public const REGEX_SALIDA = '/^OK\s+archivo=(\S+)\s+bytes=(\d+)\s+sha256=([0-9a-f]{64})/m';

    public function __construct(private ConsolaCtl $ctl) {}

    /**
     * Bases a respaldar: apps con respaldo activo (sin repetir base) y, si se configuró, la de la consola.
     *
     * @return list<array{aplicacion: ?Aplicacion, base: string, dias: int, nombre: string}>
     */
    public function objetivos(): array
    {
        $objetivos = [];
        foreach (Aplicacion::where('respaldo_activo', true)->whereNotNull('base_datos')->ordenadas()->get() as $app) {
            if (preg_match(Aplicacion::REGEX_BD, (string) $app->base_datos) && ! isset($objetivos[$app->base_datos])) {
                $objetivos[$app->base_datos] = ['aplicacion' => $app, 'base' => $app->base_datos, 'dias' => max(1, (int) $app->respaldo_dias), 'nombre' => $app->nombre];
            }
        }

        $consola = (string) config('consola.respaldos.base_consola');
        if (config('consola.respaldos.respaldar_consola') && preg_match(Aplicacion::REGEX_BD, $consola) && ! isset($objetivos[$consola])) {
            $objetivos[$consola] = ['aplicacion' => null, 'base' => $consola, 'dias' => (int) config('consola.respaldos.dias_consola', 14), 'nombre' => 'Consola Central'];
        }

        return array_values($objetivos);
    }

    /**
     * Respalda una base y purga los respaldos viejos.
     */
    public function respaldar(?Aplicacion $app, string $base, int $dias, string $origen = 'programado'): Respaldo
    {
        $inicio = hrtime(true);
        $ctl = $this->ctl->para($app);

        try {
            $resultado = $ctl->respaldoCrear($base);
            $salida = $resultado->salida;
            $ok = $resultado->ok && preg_match(self::REGEX_SALIDA, $salida, $m);
        } catch (\Throwable $e) {
            $ok = false;
            $salida = $e->getMessage();
        }

        $respaldo = Respaldo::create([
            'aplicacion_id' => $app?->id,
            'base_datos' => $base,
            'archivo' => $ok ? $m[1] : null,
            'tamano_bytes' => $ok ? (int) $m[2] : null,
            'sha256' => $ok ? $m[3] : null,
            'duracion_ms' => (int) round((hrtime(true) - $inicio) / 1e6),
            'resultado' => $ok ? 'ok' : 'error',
            'mensaje' => mb_substr(trim($salida), 0, 2000) ?: null,
            'origen' => $origen,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);

        // Solo se purga si el respaldo de hoy salió bien (nunca quedarse sin copias)
        if ($ok) {
            try {
                $ctl->respaldoPurgar($base, $dias);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $respaldo;
    }

    /** Horas desde el último respaldo correcto de la app (null si nunca). */
    public function horasSinRespaldo(Aplicacion $app): ?float
    {
        $ultimo = Respaldo::where('base_datos', $app->base_datos)->where('resultado', 'ok')->latest('created_at')->value('created_at');

        return $ultimo ? round(CarbonImmutable::parse($ultimo)->diffInMinutes(now()) / 60, 1) : null;
    }

    /**
     * Archivos de respaldo en disco (la consola puede listarlos, no leerlos).
     *
     * @return list<array{archivo: string, bytes: int, fecha: CarbonImmutable}>|null null si la carpeta no es accesible
     */
    public function archivosEnDisco(string $base): ?array
    {
        if (! preg_match(Aplicacion::REGEX_BD, $base)) {
            return null;
        }

        $carpeta = rtrim((string) config('consola.respaldos.carpeta'), '/').'/'.$base;
        if (! is_dir($carpeta) || ! is_readable($carpeta)) {
            return null;
        }

        $archivos = [];
        foreach (glob($carpeta.'/'.$base.'-*.sql.gz') ?: [] as $ruta) {
            $archivos[] = [
                'archivo' => $ruta,
                'bytes' => (int) @filesize($ruta),
                'fecha' => CarbonImmutable::createFromTimestamp((int) @filemtime($ruta), config('app.timezone')),
            ];
        }
        usort($archivos, fn ($a, $b) => $b['fecha'] <=> $a['fecha']);

        return $archivos;
    }
}
