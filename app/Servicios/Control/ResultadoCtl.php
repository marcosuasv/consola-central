<?php

namespace App\Servicios\Control;

/**
 * Resultado de una llamada a consola-ctl.
 */
final class ResultadoCtl
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?int $codigo,
        public readonly string $salida,
    ) {}

    /** Primera línea útil de la salida (para notificaciones). */
    public function resumen(int $largo = 200): string
    {
        foreach (preg_split('/\R/', trim($this->salida)) as $linea) {
            if (trim($linea) !== '') {
                return mb_strimwidth(trim($linea), 0, $largo, '…');
            }
        }

        return $this->ok ? 'Sin salida.' : 'Falló sin mensaje (código '.($this->codigo ?? '?').').';
    }
}
