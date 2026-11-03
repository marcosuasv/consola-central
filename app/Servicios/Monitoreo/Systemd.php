<?php

namespace App\Servicios\Monitoreo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

/**
 * Lecturas de systemd sin privilegios (systemctl show / journalctl).
 * Solo acepta unidades app-* o las del sistema configuradas en consola.servicios_sistema.
 */
class Systemd
{
    public const PROPIEDADES = [
        'ActiveState', 'SubState', 'MainPID', 'MemoryCurrent',
        'CPUUsageNSec', 'ActiveEnterTimestamp', 'NRestarts',
    ];

    public static function unidadPermitida(string $unidad): bool
    {
        if (preg_match('/^app-[a-z0-9-]+(\.service|\.timer)?$/', $unidad)) {
            return true;
        }

        $sistema = array_values(config('consola.servicios_sistema', []));
        $sistema[] = config('consola.servicio_fpm');

        return in_array($unidad, $sistema, true);
    }

    /**
     * Propiedades de una unidad. Si systemctl falla, regresa ActiveState = 'desconocido'.
     *
     * @return array{estado: string, subestado: ?string, pid: ?int, memoria: ?int, cpu_ns: ?int, desde: ?CarbonImmutable, reinicios: ?int}
     */
    public function propiedades(string $unidad): array
    {
        $this->validar($unidad);

        $argumentos = ['systemctl', 'show', $unidad, '--no-pager'];
        foreach (self::PROPIEDADES as $propiedad) {
            $argumentos[] = '-p';
            $argumentos[] = $propiedad;
        }

        $resultado = Process::timeout(config('consola.limites.timeout_proceso', 10))->run($argumentos);

        if (! $resultado->successful()) {
            return $this->vacio('desconocido');
        }

        $valores = [];
        foreach (preg_split('/\R/', trim($resultado->output())) as $linea) {
            if (str_contains($linea, '=')) {
                [$clave, $valor] = explode('=', $linea, 2);
                $valores[$clave] = trim($valor);
            }
        }

        return [
            'estado' => $valores['ActiveState'] ?? 'desconocido',
            'subestado' => $valores['SubState'] ?? null,
            'pid' => $this->entero($valores['MainPID'] ?? null, cerosComoNulo: true),
            'memoria' => $this->entero($valores['MemoryCurrent'] ?? null),
            'cpu_ns' => $this->entero($valores['CPUUsageNSec'] ?? null),
            'desde' => $this->fecha($valores['ActiveEnterTimestamp'] ?? null),
            'reinicios' => $this->entero($valores['NRestarts'] ?? null),
        ];
    }

    /** Solo el ActiveState (active, inactive, failed, activating...). */
    public function estado(string $unidad): string
    {
        return $this->propiedades($unidad)['estado'];
    }

    /**
     * Últimas líneas del journal de una unidad.
     *
     * @return list<string>
     */
    public function journal(string $unidad, int $lineas = 100): array
    {
        $this->validar($unidad);
        $lineas = max(1, min($lineas, (int) config('consola.limites.lineas_log', 500)));

        $resultado = Process::timeout(config('consola.limites.timeout_proceso', 10))->run([
            'journalctl', '-u', $unidad, '-n', (string) $lineas, '--no-pager', '-o', 'short-iso',
        ]);

        if (! $resultado->successful()) {
            return ['(no se pudo leer el journal: '.trim($resultado->errorOutput()).')'];
        }

        return array_values(array_filter(preg_split('/\R/', $resultado->output()), fn ($l) => $l !== ''));
    }

    private function validar(string $unidad): void
    {
        if (! self::unidadPermitida($unidad)) {
            throw new InvalidArgumentException("Unidad no permitida: {$unidad}");
        }
    }

    private function entero(?string $valor, bool $cerosComoNulo = false): ?int
    {
        if ($valor === null || ! ctype_digit($valor)) {
            return null; // "[not set]", "infinity", vacío
        }

        $numero = (int) $valor;

        // systemd usa 2^64-1 para "sin dato"
        if ($valor === '18446744073709551615' || ($cerosComoNulo && $numero === 0)) {
            return null;
        }

        return $numero;
    }

    private function fecha(?string $valor): ?CarbonImmutable
    {
        if (blank($valor) || $valor === 'n/a') {
            return null;
        }

        try {
            // Formato: "Tue 2026-11-03 15:05:03 CST"
            $sinDia = preg_replace('/^[A-Za-z]{3}\s+/', '', $valor);

            return CarbonImmutable::parse(preg_replace('/\s+[A-Z]{2,5}$/', '', $sinDia), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function vacio(string $estado): array
    {
        return ['estado' => $estado, 'subestado' => null, 'pid' => null, 'memoria' => null, 'cpu_ns' => null, 'desde' => null, 'reinicios' => null];
    }
}
