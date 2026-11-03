<?php

namespace App\Servicios\Monitoreo;

use App\Models\Aplicacion;
use Carbon\CarbonImmutable;

/**
 * Lectura del FINAL de archivos de log, con límite de bytes y líneas.
 * Nunca carga un archivo completo y solo lee rutas permitidas.
 */
class LectorLog
{
    /** Patrón de una entrada de Laravel: [2026-11-03 15:05:03] production.ERROR: ... */
    public const REGEX_ENTRADA = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s+[\w-]+\.([A-Z]+):/';

    public const NIVELES_ERROR = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /** Ruta del laravel*.log más reciente de la app (o null). */
    public function logLaravel(Aplicacion $app): ?string
    {
        $archivos = @glob($app->rutaLogs().'/laravel*.log') ?: [];

        usort($archivos, fn ($a, $b) => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));

        return $archivos[0] ?? null;
    }

    /**
     * Últimas $lineas líneas del archivo.
     *
     * @return list<string>|null null si no existe o no se puede leer
     */
    public function cola(?string $ruta, int $lineas = 200): ?array
    {
        if ($ruta === null || ! $this->rutaPermitida($ruta) || ! is_file($ruta) || ! is_readable($ruta)) {
            return null;
        }

        $lineas = max(1, min($lineas, (int) config('consola.limites.lineas_log', 500)));
        $maxBytes = (int) config('consola.limites.bytes_log', 1024 * 1024);

        $manejador = @fopen($ruta, 'rb');
        if ($manejador === false) {
            return null;
        }

        try {
            $tamano = fstat($manejador)['size'] ?? 0;
            $posicion = max(0, $tamano - $maxBytes);
            fseek($manejador, $posicion);
            $buffer = $tamano > 0 ? (string) fread($manejador, $tamano - $posicion) : '';
        } finally {
            fclose($manejador);
        }

        if ($buffer === '') {
            return [];
        }

        $todas = preg_split('/\R/', rtrim($buffer, "\r\n"));

        // La primera línea puede venir cortada si no empezamos al inicio del archivo
        if ($posicion > 0 && count($todas) > 1) {
            array_shift($todas);
        }

        return array_values(array_slice($todas, -$lineas));
    }

    /**
     * Cuenta entradas de nivel error en las últimas 24 h (sobre el final del log).
     */
    public function erroresUltimas24h(Aplicacion $app): ?int
    {
        $lineas = $this->cola($this->logLaravel($app), (int) config('consola.limites.lineas_log', 500) * 20);

        if ($lineas === null) {
            return null;
        }

        $desde = CarbonImmutable::now()->subDay();
        $total = 0;

        foreach ($lineas as $linea) {
            if (preg_match(self::REGEX_ENTRADA, $linea, $m) && in_array($m[2], self::NIVELES_ERROR, true)) {
                try {
                    if (CarbonImmutable::parse($m[1])->greaterThanOrEqualTo($desde)) {
                        $total++;
                    }
                } catch (\Throwable) {
                    // fecha ilegible: se ignora
                }
            }
        }

        return $total;
    }

    /**
     * Agrupa líneas de Laravel en entradas (una entrada + su stack trace) y filtra.
     *
     * @param  list<string>  $lineas
     * @return list<array{fecha: ?string, nivel: string, texto: string}>
     */
    public function entradasLaravel(array $lineas, ?string $nivel = null, ?string $buscar = null): array
    {
        $entradas = [];
        $actual = null;

        foreach ($lineas as $linea) {
            if (preg_match(self::REGEX_ENTRADA, $linea, $m)) {
                if ($actual) {
                    $entradas[] = $actual;
                }
                $actual = ['fecha' => $m[1], 'nivel' => $m[2], 'texto' => $linea];
            } elseif ($actual) {
                // Se conservan solo las primeras líneas del stack trace
                if (substr_count($actual['texto'], "\n") < 15) {
                    $actual['texto'] .= "\n".$linea;
                }
            } else {
                $actual = ['fecha' => null, 'nivel' => 'INFO', 'texto' => $linea];
            }
        }
        if ($actual) {
            $entradas[] = $actual;
        }

        $entradas = array_filter($entradas, function ($e) use ($nivel, $buscar) {
            if ($nivel === 'ERROR' && ! in_array($e['nivel'], self::NIVELES_ERROR, true)) {
                return false;
            }
            if ($nivel === 'WARNING' && ! in_array($e['nivel'], [...self::NIVELES_ERROR, 'WARNING'], true)) {
                return false;
            }

            return blank($buscar) || mb_stripos($e['texto'], $buscar) !== false;
        });

        return array_map(fn ($e) => [...$e, 'texto' => self::ocultarSecretos($e['texto'])], array_values($entradas));
    }

    /** Oculta posibles secretos antes de mostrarlos en pantalla. */
    public static function ocultarSecretos(string $texto): string
    {
        $patrones = [
            '/(APP_KEY\s*[=:]\s*)\S+/i' => '$1••••••',
            '/base64:[A-Za-z0-9+\/=]{20,}/' => 'base64:••••••',
            '/((?:password|passwd|pwd|secret|contraseña)["\']?\s*[=:]>?\s*["\']?)[^\s"\'&,;]+/iu' => '$1••••••',
            '/((?:api[_-]?key|access[_-]?token|token|bearer)["\']?\s*[=:]?>?\s*["\']?)[A-Za-z0-9._\-+\/]{8,}/i' => '$1••••••',
            '#(mysql|https?|smtp)://([^:/\s]+):[^@\s]+@#i' => '$1://$2:••••••@',
        ];

        return preg_replace(array_keys($patrones), array_values($patrones), $texto) ?? $texto;
    }

    private function rutaPermitida(string $ruta): bool
    {
        if (str_contains($ruta, '..') || str_contains($ruta, "\0")) {
            return false;
        }

        $permitidas = [
            '#^'.preg_quote(Aplicacion::RUTA_BASE, '#').'/[A-Za-z0-9_-]+/storage/logs/[A-Za-z0-9._-]+\.log$#',
            '#^/var/log/nginx/[A-Za-z0-9._-]+\.log$#',
            '#^/var/log/(fail2ban|consola-ctl)\.log$#',
        ];

        // Las pruebas automáticas leen desde storage/
        if (app()->runningUnitTests()) {
            $permitidas[] = '#^'.preg_quote(storage_path(), '#').'/#';
        }

        foreach ($permitidas as $regex) {
            if (preg_match($regex, $ruta)) {
                return true;
            }
        }

        return false;
    }
}
