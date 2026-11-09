<?php

namespace App\Servicios\Seguridad;

use App\Servicios\Control\ConsolaCtl;
use Illuminate\Support\Facades\Cache;

/**
 * Estado actual de Fail2ban (vía consola-ctl f2b status) e historial desde /var/log/fail2ban.log.
 */
class Fail2ban
{
    public const LOG = '/var/log/fail2ban.log';

    /** Patrón de una línea del log: 2026-11-03 15:59:50,123 fail2ban.actions [123]: NOTICE  [sshd] Ban 1.2.3.4 */
    public const REGEX_LOG = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+\s+fail2ban\.\w+\s+\[\d+\]:\s+\w+\s+\[([^\]]+)\]\s+(Ban|Unban|Restore Ban|Found)\s+(\S+)/';

    public function __construct(private ConsolaCtl $ctl) {}

    /**
     * @return array{reglas: array<string, array{actuales: int, total: int, fallos: int, ips: list<string>}>, error: ?string}
     */
    public function estado(bool $refrescar = false): array
    {
        if ($refrescar) {
            Cache::forget('consola.f2b.estado');
        }

        return Cache::remember('consola.f2b.estado', 60, function (): array {
            $general = $this->ctl->f2bStatus();
            if (! $general->ok) {
                return ['reglas' => [], 'error' => $general->resumen()];
            }

            $reglas = [];
            foreach (self::reglasDesdeSalida($general->salida) as $regla) {
                $detalle = $this->ctl->f2bStatus($regla);
                $reglas[$regla] = $detalle->ok
                    ? self::detalleDesdeSalida($detalle->salida)
                    : ['actuales' => 0, 'total' => 0, 'fallos' => 0, 'ips' => [], 'error' => $detalle->resumen()];
            }

            return ['reglas' => $reglas, 'error' => null];
        });
    }

    public function olvidarEstado(): void
    {
        Cache::forget('consola.f2b.estado');
    }

    /** @return list<string> */
    public static function reglasDesdeSalida(string $salida): array
    {
        if (! preg_match('/Jail list:\s*(.*)$/mi', $salida, $m)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $m[1])),
            fn ($r) => preg_match(ConsolaCtl::REGEX_REGLA_F2B, $r),
        ));
    }

    /** @return array{actuales: int, total: int, fallos: int, ips: list<string>} */
    public static function detalleDesdeSalida(string $salida): array
    {
        $numero = fn (string $etiqueta) => preg_match('/'.$etiqueta.':\s*(\d+)/i', $salida, $m) ? (int) $m[1] : 0;
        $ips = preg_match('/Banned IP list:\s*(.*)$/mi', $salida, $m)
            ? array_values(array_filter(preg_split('/\s+/', trim($m[1])), fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP)))
            : [];

        return [
            'actuales' => $numero('Currently banned'),
            'total' => $numero('Total banned'),
            'fallos' => $numero('Currently failed'),
            'ips' => $ips,
        ];
    }

    /**
     * Eventos de los últimos días leyendo el final de fail2ban.log (y fail2ban.log.1 si existe).
     *
     * @return list<array{fecha: string, regla: string, tipo: string, ip: string}>
     */
    public function historial(int $dias = 7, ?string $ruta = null): array
    {
        $ruta ??= self::LOG;
        $desde = now()->subDays($dias)->format('Y-m-d H:i:s');
        $limite = (int) config('consola.limites.bytes_seguridad', 8 * 1024 * 1024);
        $eventos = [];

        foreach (array_reverse([$ruta.'.1', $ruta]) as $archivo) {
            foreach (LectorArchivo::final($archivo, $limite) as $linea) {
                if (preg_match(self::REGEX_LOG, $linea, $m) && $m[1] >= $desde && filter_var($m[4], FILTER_VALIDATE_IP)) {
                    $eventos[] = ['fecha' => $m[1], 'regla' => $m[2], 'tipo' => $m[3], 'ip' => $m[4]];
                }
            }
        }

        usort($eventos, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));

        return $eventos;
    }

    /**
     * Resumen del historial: bloqueos por día y regla, IPs más bloqueadas, intentos detectados.
     *
     * @param  list<array{fecha: string, regla: string, tipo: string, ip: string}>  $eventos
     */
    public static function resumir(array $eventos, int $dias = 7): array
    {
        $porDia = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $porDia[now()->subDays($i)->format('Y-m-d')] = 0;
        }

        $porRegla = [];
        $ips = [];
        $intentos = 0;

        foreach ($eventos as $e) {
            if ($e['tipo'] === 'Found') {
                $intentos++;

                continue;
            }
            if ($e['tipo'] !== 'Ban') {
                continue;
            }
            $dia = substr($e['fecha'], 0, 10);
            if (isset($porDia[$dia])) {
                $porDia[$dia]++;
            }
            $porRegla[$e['regla']] = ($porRegla[$e['regla']] ?? 0) + 1;
            $ips[$e['ip']] ??= ['ip' => $e['ip'], 'bloqueos' => 0, 'reglas' => [], 'ultimo' => $e['fecha']];
            $ips[$e['ip']]['bloqueos']++;
            $ips[$e['ip']]['reglas'][$e['regla']] = true;
            $ips[$e['ip']]['ultimo'] = $e['fecha'];
        }

        arsort($porRegla);
        usort($ips, fn ($a, $b) => [$b['bloqueos'], $b['ultimo']] <=> [$a['bloqueos'], $a['ultimo']]);

        return [
            'por_dia' => $porDia,
            'por_regla' => $porRegla,
            'ips' => array_map(fn ($i) => [...$i, 'reglas' => array_keys($i['reglas'])], array_slice($ips, 0, 20)),
            'bloqueos' => array_sum($porDia),
            'intentos' => $intentos,
            'recientes' => array_slice(array_reverse(array_values(array_filter($eventos, fn ($e) => $e['tipo'] !== 'Found'))), 0, 50),
        ];
    }
}
