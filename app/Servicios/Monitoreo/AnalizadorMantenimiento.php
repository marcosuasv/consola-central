<?php

namespace App\Servicios\Monitoreo;

use App\Models\Aplicacion;
use App\Models\EstadoApp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

/**
 * Información de mantenimiento y recomendaciones por reglas (sin IA).
 * El análisis completo (composer audit/outdated) llega con el informe diario, Paso 12.
 */
class AnalizadorMantenimiento
{
    public function versionLaravel(Aplicacion $app): ?string
    {
        $archivo = $app->ruta().'/vendor/laravel/framework/src/Illuminate/Foundation/Application.php';
        $contenido = is_readable($archivo) ? @file_get_contents($archivo, false, null, 0, 64 * 1024) : false;

        return $contenido && preg_match("/const VERSION = '([0-9.]+)'/", $contenido, $m) ? $m[1] : null;
    }

    /**
     * @return array{estado: string, semaforo: string, texto: string, seguridad_hasta: ?string}
     */
    public function soporteLaravel(?string $version): array
    {
        if ($version === null) {
            return ['estado' => 'desconocido', 'semaforo' => Semaforo::GRIS, 'texto' => 'No se pudo leer la versión de Laravel.', 'seguridad_hasta' => null];
        }

        $mayor = (int) $version;
        $soporte = config("consola.soporte_laravel.{$mayor}");
        $hoy = CarbonImmutable::today();

        if (! $soporte) {
            return $mayor > max(array_keys(config('consola.soporte_laravel')))
                ? ['estado' => 'vigente', 'semaforo' => Semaforo::VERDE, 'texto' => "Laravel {$mayor} con soporte.", 'seguridad_hasta' => null]
                : ['estado' => 'sin_soporte', 'semaforo' => Semaforo::ROJO, 'texto' => "Laravel {$mayor} sin soporte oficial.", 'seguridad_hasta' => null];
        }

        $seguridad = CarbonImmutable::parse($soporte['seguridad']);
        $errores = CarbonImmutable::parse($soporte['errores']);

        return match (true) {
            $hoy->greaterThan($seguridad) => [
                'estado' => 'sin_soporte', 'semaforo' => Semaforo::ROJO,
                'texto' => "Laravel {$mayor} sin parches de seguridad desde ".$seguridad->translatedFormat('F Y').'.',
                'seguridad_hasta' => $seguridad->toDateString(),
            ],
            $hoy->greaterThan($errores) => [
                'estado' => 'solo_seguridad', 'semaforo' => Semaforo::AMARILLO,
                'texto' => "Laravel {$mayor} solo recibe parches de seguridad hasta ".$seguridad->translatedFormat('j \\d\\e F \\d\\e Y').'.',
                'seguridad_hasta' => $seguridad->toDateString(),
            ],
            default => [
                'estado' => 'vigente', 'semaforo' => Semaforo::VERDE,
                'texto' => "Laravel {$mayor} con soporte completo.",
                'seguridad_hasta' => $seguridad->toDateString(),
            ],
        };
    }

    /** Archivos con cambios sin commitear (null si no es repo o no se pudo leer). */
    public function cambiosGit(Aplicacion $app): ?int
    {
        if (! is_dir($app->ruta().'/.git')) {
            return null;
        }

        $resultado = Process::timeout(config('consola.limites.timeout_proceso', 10))->run([
            'git', '-c', 'safe.directory=*', '-C', $app->ruta(), 'status', '--porcelain',
        ]);

        if (! $resultado->successful()) {
            return null;
        }

        return count(array_filter(preg_split('/\R/', $resultado->output())));
    }

    /**
     * Recomendaciones en texto según reglas.
     *
     * @return list<array{nivel: string, texto: string}>
     */
    public function recomendaciones(Aplicacion $app, ?EstadoApp $estado, array $soporte, ?int $cambiosGit): array
    {
        $r = [];
        $agregar = function (string $nivel, string $texto) use (&$r): void {
            $r[] = ['nivel' => $nivel, 'texto' => $texto];
        };

        if ($soporte['estado'] === 'sin_soporte') {
            $agregar(Semaforo::ROJO, 'Planear la migración a una versión de Laravel con soporte (12 o superior). Sin soporte no hay parches de seguridad.');
        } elseif ($soporte['estado'] === 'solo_seguridad') {
            $agregar(Semaforo::AMARILLO, 'Programar la actualización a la siguiente versión mayor de Laravel antes del '.CarbonImmutable::parse($soporte['seguridad_hasta'])->format('d/m/Y').'.');
        }

        if ($estado) {
            if (($estado->errores_log_24h ?? 0) > 0) {
                $agregar(Semaforo::AMARILLO, 'Revisar los errores del log de Laravel (pestaña Log) y corregir el más frecuente.');
            }
            if (($estado->jobs_fallidos_24h ?? 0) > 0) {
                $agregar(Semaforo::AMARILLO, 'Revisar los jobs fallidos (pestaña Colas): reintentar o corregir la causa.');
            }
            if (($estado->latencia_ms ?? 0) > config('consola.umbrales.latencia_ms')) {
                $agregar(Semaforo::AMARILLO, 'La app responde lento: revisar consultas pesadas, caché de configuración (php artisan optimize) y memoria del servidor.');
            }
            if (($estado->reinicios_24h ?? 0) > 1) {
                $agregar(Semaforo::AMARILLO, 'La app se reinició varias veces: revisar el journal de systemd por errores de memoria o excepciones al arrancar.');
            }
            if (($estado->jobs_pendientes ?? 0) > config('consola.umbrales.pendientes')) {
                $agregar(Semaforo::AMARILLO, 'La cola se está acumulando: verificar que el worker esté activo o agregar más workers.');
            }
        }

        if ($cambiosGit) {
            $agregar(Semaforo::GRIS, $app->carpeta === 'akoc-control'
                ? "Hay {$cambiosGit} archivos sin commitear (esperado en esta app)."
                : "Hay {$cambiosGit} archivos sin commitear en el servidor: subirlos al repositorio o descartarlos.");
        }

        if ($app->publica_internet) {
            $agregar(Semaforo::GRIS, 'App publicada a internet: revisar a diario los bloqueos de Fail2ban y las visitas desde IPs públicas.');
        }

        return $r;
    }
}
