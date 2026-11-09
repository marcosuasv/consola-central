<?php

namespace App\Servicios\Monitoreo;

use App\Servicios\Seguridad\RegistroConsolaCtl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

/**
 * Lecturas del servidor: carga, RAM, disco, servicios base, montaje CIFS y certificado.
 */
class LectorSistema
{
    public function __construct(private Systemd $systemd, private RegistroConsolaCtl $registroCtl) {}

    /** @return array<string, mixed> columnas de estados_sistema (sin semáforo) */
    public function leer(): array
    {
        $carga = $this->carga();
        $ram = $this->ram();
        $total = @disk_total_space('/') ?: null;
        $libre = @disk_free_space('/') ?: null;

        $servicios = [];
        foreach (config('consola.servicios_sistema', []) as $nombre => $unidad) {
            $servicios[$nombre] = $this->systemd->estado($unidad);
        }

        return [
            'carga_1' => $carga[0] ?? null,
            'carga_5' => $carga[1] ?? null,
            'carga_15' => $carga[2] ?? null,
            'cpus' => $this->cpus(),
            'ram_total' => $ram['total'] ?? null,
            'ram_usada' => isset($ram['total'], $ram['disponible']) ? $ram['total'] - $ram['disponible'] : null,
            'disco_total' => $total ? (int) $total : null,
            'disco_usado' => $total && $libre !== null ? (int) ($total - $libre) : null,
            'servicios' => $servicios,
            'montaje_facturas' => $this->montado(config('consola.montaje_facturas')),
            'cert_dias' => $this->diasCertificado(config('consola.certificado')),
        ];
    }

    /**
     * @param  array<string, mixed>  $lectura
     * @return array{semaforo: string, alertas: list<array{nivel: string, mensaje: string}>}
     */
    public function evaluar(array $lectura): array
    {
        $c = config('consola.sistema');
        $alertas = [];

        foreach ($lectura['servicios'] ?? [] as $nombre => $estado) {
            if ($estado !== 'active') {
                $alertas[] = ['nivel' => Semaforo::ROJO, 'mensaje' => "{$nombre} está ".EvaluadorApp::estadoLegible($estado).'.'];
            }
        }

        if ($lectura['montaje_facturas'] === false) {
            $alertas[] = ['nivel' => Semaforo::ROJO, 'mensaje' => 'El montaje de facturas ('.config('consola.montaje_facturas').') no está disponible.'];
        }

        $disco = $lectura['disco_total'] ? (int) round($lectura['disco_usado'] * 100 / $lectura['disco_total']) : null;
        if ($disco !== null && $disco >= $c['disco_amarillo']) {
            $alertas[] = ['nivel' => $disco >= $c['disco_rojo'] ? Semaforo::ROJO : Semaforo::AMARILLO, 'mensaje' => "Disco / al {$disco} %."];
        }

        $ram = $lectura['ram_total'] ? (int) round($lectura['ram_usada'] * 100 / $lectura['ram_total']) : null;
        if ($ram !== null && $ram >= $c['ram_amarillo']) {
            $alertas[] = ['nivel' => $ram >= $c['ram_rojo'] ? Semaforo::ROJO : Semaforo::AMARILLO, 'mensaje' => "RAM al {$ram} %."];
        }

        if ($lectura['cert_dias'] !== null && $lectura['cert_dias'] <= $c['cert_dias_amarillo']) {
            $alertas[] = [
                'nivel' => $lectura['cert_dias'] <= $c['cert_dias_rojo'] ? Semaforo::ROJO : Semaforo::AMARILLO,
                'mensaje' => $lectura['cert_dias'] < 0 ? 'El certificado TLS ya venció.' : "El certificado TLS vence en {$lectura['cert_dias']} días.",
            ];
        }

        if ($lectura['cpus'] && $lectura['carga_5'] !== null && $lectura['carga_5'] > $lectura['cpus']) {
            $alertas[] = ['nivel' => Semaforo::AMARILLO, 'mensaje' => "Carga alta: {$lectura['carga_5']} (5 min) con {$lectura['cpus']} CPU."];
        }

        // Acciones rechazadas por consola-ctl = alguien intentó algo fuera de lo permitido
        $rechazos = $this->registroCtl->rechazosDesde(CarbonImmutable::now()->subDay());
        if ($rechazos > 0) {
            $alertas[] = ['nivel' => Semaforo::AMARILLO, 'mensaje' => "consola-ctl rechazó {$rechazos} ".($rechazos === 1 ? 'acción' : 'acciones').' en 24 h (ver Seguridad).'];
        }

        return ['semaforo' => Semaforo::peor(Semaforo::VERDE, ...array_column($alertas, 'nivel')), 'alertas' => $alertas];
    }

    private function carga(): array
    {
        $texto = @file_get_contents('/proc/loadavg');

        return $texto ? array_map('floatval', array_slice(explode(' ', $texto), 0, 3)) : [];
    }

    private function cpus(): ?int
    {
        $texto = @file_get_contents('/proc/cpuinfo');

        return $texto ? max(1, preg_match_all('/^processor\s*:/m', $texto)) : null;
    }

    private function ram(): array
    {
        $texto = @file_get_contents('/proc/meminfo');
        if (! $texto) {
            return [];
        }

        preg_match('/^MemTotal:\s+(\d+)/m', $texto, $total);
        preg_match('/^MemAvailable:\s+(\d+)/m', $texto, $disponible);

        return [
            'total' => isset($total[1]) ? (int) $total[1] * 1024 : null,
            'disponible' => isset($disponible[1]) ? (int) $disponible[1] * 1024 : null,
        ];
    }

    private function montado(?string $ruta): ?bool
    {
        if (blank($ruta)) {
            return null;
        }

        $resultado = Process::timeout(config('consola.limites.timeout_proceso', 10))
            ->run(['findmnt', '-n', '-o', 'TARGET', '--mountpoint', $ruta]);

        return $resultado->successful() && trim($resultado->output()) !== '';
    }

    private function diasCertificado(?string $ruta): ?int
    {
        if (blank($ruta) || ! is_readable($ruta)) {
            return null;
        }

        $datos = @openssl_x509_parse((string) @file_get_contents($ruta, false, null, 0, 64 * 1024));
        if (! $datos || empty($datos['validTo_time_t'])) {
            return null;
        }

        return (int) floor(($datos['validTo_time_t'] - time()) / 86400);
    }
}
