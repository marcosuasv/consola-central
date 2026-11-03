<?php

namespace App\Servicios\Monitoreo;

use App\Models\Aplicacion;

/**
 * Calcula el semáforo y las alertas de una app a partir de una lectura.
 *
 * Rojo: servicio inactivo (si no es de pruebas), salud fuera de 2xx/3xx, más de N fallidos en 24 h.
 * Amarillo: latencia alta, reinicios en 24 h, errores en log, muchos pendientes.
 * Gris: app de pruebas apagada o monitoreo desactivado.
 */
class EvaluadorApp
{
    /**
     * @param  array<string, mixed>  $lectura  columnas de estados_app
     * @return array{semaforo: string, alertas: list<array{nivel: string, mensaje: string}>}
     */
    public function evaluar(Aplicacion $app, array $lectura): array
    {
        if (! $app->monitorear) {
            return ['semaforo' => Semaforo::GRIS, 'alertas' => []];
        }

        $activo = ($lectura['servicio_estado'] ?? null) === 'active';

        if ($app->es_pruebas && ! $activo) {
            return ['semaforo' => Semaforo::GRIS, 'alertas' => [
                ['nivel' => Semaforo::GRIS, 'mensaje' => 'Entorno de pruebas apagado (normal).'],
            ]];
        }

        $u = config('consola.umbrales');
        $alertas = [];
        $rojo = function (string $m) use (&$alertas): void {
            $alertas[] = ['nivel' => Semaforo::ROJO, 'mensaje' => $m];
        };
        $amarillo = function (string $m) use (&$alertas): void {
            $alertas[] = ['nivel' => Semaforo::AMARILLO, 'mensaje' => $m];
        };

        $nombreServicio = $app->esFpm() ? 'PHP-FPM' : 'El servicio';

        if (! $activo) {
            $rojo("{$nombreServicio} está ".self::estadoLegible($lectura['servicio_estado'] ?? null).'.');
        }

        if (! ProbadorSalud::codigoOk($lectura['http_codigo'] ?? null)) {
            $rojo(isset($lectura['http_codigo'])
                ? "La prueba de salud respondió HTTP {$lectura['http_codigo']}."
                : 'La prueba de salud no respondió'.(! empty($lectura['http_error']) ? ': '.mb_substr($lectura['http_error'], 0, 80) : '.'));
        }

        if (($lectura['jobs_fallidos_24h'] ?? 0) > $u['fallidos_24h']) {
            $rojo("{$lectura['jobs_fallidos_24h']} jobs fallidos en 24 h.");
        } elseif (($lectura['jobs_fallidos_24h'] ?? 0) > 0) {
            $amarillo("{$lectura['jobs_fallidos_24h']} jobs fallidos en 24 h.");
        }

        if ($app->esOctane() && $app->servicio_cola && ($lectura['cola_estado'] ?? null) !== null && $lectura['cola_estado'] !== 'active') {
            $amarillo('El worker de la cola está '.self::estadoLegible($lectura['cola_estado']).'.');
        }

        if (($lectura['latencia_ms'] ?? 0) > $u['latencia_ms']) {
            $amarillo('Respuesta lenta: '.number_format($lectura['latencia_ms'] / 1000, 1).' s.');
        }

        if (($lectura['reinicios_24h'] ?? 0) > 0) {
            $amarillo("Se reinició {$lectura['reinicios_24h']} ".($lectura['reinicios_24h'] === 1 ? 'vez' : 'veces').' en 24 h.');
        }

        if (($lectura['errores_log_24h'] ?? 0) > 0) {
            $amarillo("{$lectura['errores_log_24h']} errores en laravel.log en 24 h.");
        }

        if (($lectura['jobs_pendientes'] ?? 0) > $u['pendientes']) {
            $amarillo("{$lectura['jobs_pendientes']} jobs pendientes en la cola.");
        }

        $semaforo = Semaforo::peor(Semaforo::VERDE, ...array_column($alertas, 'nivel'));

        return ['semaforo' => $semaforo, 'alertas' => $alertas];
    }

    public static function estadoLegible(?string $estado): string
    {
        return match ($estado) {
            'active' => 'activo',
            'inactive' => 'detenido',
            'failed' => 'en falla',
            'activating' => 'arrancando',
            'deactivating' => 'deteniéndose',
            'reloading' => 'recargando',
            null, 'desconocido' => 'en estado desconocido',
            default => $estado,
        };
    }
}
