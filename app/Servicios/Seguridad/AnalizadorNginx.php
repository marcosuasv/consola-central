<?php

namespace App\Servicios\Seguridad;

use App\Models\Aplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Visitas desde IPs públicas por app, leyendo el final del access.log de Nginx (formato combined).
 */
class AnalizadorNginx
{
    /** 1.2.3.4 - - [03/Nov/2026:15:12:09 -0600] "GET /ruta HTTP/1.1" 200 123 "ref" "agente" */
    public const REGEX = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(?:([A-Z]{3,10}) (\S+)[^"]*|[^"]*)" (\d{3}) (?:\d+|-)(?: "[^"]*" "([^"]*)")?/';

    /** Rutas típicas de escaneos automáticos. */
    public const SOSPECHOSAS = '#(\.env|\.git/|wp-login|wp-admin|wp-content|xmlrpc\.php|phpmyadmin|pma/|/cgi-bin/|\.\./|/vendor/phpunit|/actuator|/boaform|/HNAP1|/shell|\.aspx?$|/config\.json|/server-status|/\.aws|/etc/passwd|eval-stdin)#i';

    /**
     * @return array{
     *   disponible: bool, desde: ?string, recortado: bool,
     *   total: int, publicas: int, ips_publicas: int, errores_4xx: int, errores_5xx: int, sospechosas: int,
     *   top_ips: list<array{ip: string, peticiones: int, e4xx: int, e5xx: int, sospechosas: int, ultima: string, agente: ?string}>,
     *   top_rutas: list<array{ruta: string, peticiones: int, sospechosa: bool}>,
     *   por_hora: list<int>
     * }
     */
    public function analizar(Aplicacion $app, int $horas = 24): array
    {
        return Cache::remember("consola.nginx.{$app->id}.{$horas}", 300, fn () => $this->analizarArchivo($app->log_nginx, $horas));
    }

    public function analizarArchivo(?string $ruta, int $horas = 24): array
    {
        $resultado = [
            'disponible' => false, 'desde' => null, 'recortado' => false,
            'total' => 0, 'publicas' => 0, 'ips_publicas' => 0, 'errores_4xx' => 0, 'errores_5xx' => 0, 'sospechosas' => 0,
            'top_ips' => [], 'top_rutas' => [], 'por_hora' => array_fill(0, $horas, 0),
        ];

        if (blank($ruta) || ! is_readable($ruta)) {
            return $resultado;
        }

        $limite = (int) config('consola.limites.bytes_seguridad', 8 * 1024 * 1024);
        $lineas = LectorArchivo::final($ruta, $limite);
        $resultado['disponible'] = true;
        $resultado['recortado'] = (@filesize($ruta) ?: 0) > $limite;

        $desde = CarbonImmutable::now()->subHours($horas);
        $ips = [];
        $rutas = [];
        $primera = null;

        foreach ($lineas as $linea) {
            if (! preg_match(self::REGEX, $linea, $m)) {
                continue;
            }

            try {
                $fecha = CarbonImmutable::createFromFormat('d/M/Y:H:i:s O', $m[2]);
            } catch (\Throwable) {
                continue;
            }
            if (! $fecha || $fecha->lessThan($desde)) {
                continue;
            }

            $primera ??= $fecha;
            $resultado['total']++;

            $ip = $m[1];
            if (! Ip::esPublica($ip)) {
                continue;
            }

            $codigo = (int) $m[5];
            $rutaPedida = mb_substr(strtok($m[4] ?? '-', '?') ?: '-', 0, 120);
            $sospechosa = (bool) preg_match(self::SOSPECHOSAS, $m[4] ?? '');

            $resultado['publicas']++;
            $resultado['errores_4xx'] += $codigo >= 400 && $codigo < 500 ? 1 : 0;
            $resultado['errores_5xx'] += $codigo >= 500 ? 1 : 0;
            $resultado['sospechosas'] += $sospechosa ? 1 : 0;

            $hora = min($horas - 1, max(0, intdiv($fecha->getTimestamp() - $desde->getTimestamp(), 3600)));
            $resultado['por_hora'][$hora]++;

            $ips[$ip] ??= ['ip' => $ip, 'peticiones' => 0, 'e4xx' => 0, 'e5xx' => 0, 'sospechosas' => 0, 'ultima' => '', 'agente' => null];
            $ips[$ip]['peticiones']++;
            $ips[$ip]['e4xx'] += $codigo >= 400 && $codigo < 500 ? 1 : 0;
            $ips[$ip]['e5xx'] += $codigo >= 500 ? 1 : 0;
            $ips[$ip]['sospechosas'] += $sospechosa ? 1 : 0;
            $ips[$ip]['ultima'] = $fecha->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            $ips[$ip]['agente'] = isset($m[6]) ? mb_substr($m[6], 0, 160) : null;

            $rutas[$rutaPedida] ??= ['ruta' => $rutaPedida, 'peticiones' => 0, 'sospechosa' => false];
            $rutas[$rutaPedida]['peticiones']++;
            $rutas[$rutaPedida]['sospechosa'] = $rutas[$rutaPedida]['sospechosa'] || $sospechosa;
        }

        usort($ips, fn ($a, $b) => [$b['sospechosas'], $b['peticiones']] <=> [$a['sospechosas'], $a['peticiones']]);
        usort($rutas, fn ($a, $b) => [$b['sospechosa'], $b['peticiones']] <=> [$a['sospechosa'], $a['peticiones']]);

        $resultado['ips_publicas'] = count($ips);
        $resultado['top_ips'] = array_slice($ips, 0, 25);
        $resultado['top_rutas'] = array_slice($rutas, 0, 15);
        $resultado['desde'] = $primera?->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

        return $resultado;
    }
}
