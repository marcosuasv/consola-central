<?php

namespace App\Servicios\Seguridad;

use Carbon\CarbonImmutable;

/**
 * Lectura de /var/log/consola-ctl.log. Las líneas «RECHAZADO» son intentos fuera de lo permitido: alerta.
 */
class RegistroConsolaCtl
{
    public const LOG = '/var/log/consola-ctl.log';

    /** 2026-11-03 15:59:50 usuario=consola RECHAZADO: unidad no permitida: ssh */
    public const REGEX = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+usuario=(\S+)\s+(.*)$/';

    /**
     * @return list<array{fecha: string, usuario: string, mensaje: string, rechazado: bool}> más reciente primero
     */
    public function entradas(int $maximo = 200, ?string $ruta = null): array
    {
        $entradas = [];
        foreach (LectorArchivo::final($ruta ?? self::LOG, 512 * 1024) as $linea) {
            if (preg_match(self::REGEX, $linea, $m)) {
                $entradas[] = [
                    'fecha' => $m[1],
                    'usuario' => $m[2],
                    'mensaje' => mb_substr($m[3], 0, 300),
                    'rechazado' => str_starts_with($m[3], 'RECHAZADO'),
                ];
            }
        }

        return array_slice(array_reverse($entradas), 0, $maximo);
    }

    public function rechazosDesde(CarbonImmutable $desde, ?string $ruta = null): int
    {
        $limite = $desde->format('Y-m-d H:i:s');

        return count(array_filter($this->entradas(1000, $ruta), fn ($e) => $e['rechazado'] && $e['fecha'] >= $limite));
    }
}
