<?php

namespace App\Servicios\Monitoreo;

/**
 * Colores del semáforo y su prioridad. Reglas en AGENTS.md, Paso 6.
 */
final class Semaforo
{
    public const VERDE = 'verde';

    public const AMARILLO = 'amarillo';

    public const ROJO = 'rojo';

    public const GRIS = 'gris';

    private const PRIORIDAD = [self::GRIS => 0, self::VERDE => 1, self::AMARILLO => 2, self::ROJO => 3];

    /** El peor de los colores dados (gris es neutro). */
    public static function peor(string ...$colores): string
    {
        $peor = self::VERDE;
        foreach ($colores as $color) {
            if ((self::PRIORIDAD[$color] ?? 0) > self::PRIORIDAD[$peor]) {
                $peor = $color;
            }
        }

        return $peor;
    }

    public static function etiqueta(?string $color): string
    {
        return match ($color) {
            self::VERDE => 'Operando',
            self::AMARILLO => 'Atención',
            self::ROJO => 'Falla',
            self::GRIS => 'Sin vigilancia',
            default => 'Sin datos',
        };
    }

    /** Color de Filament equivalente. */
    public static function colorFilament(?string $color): string
    {
        return match ($color) {
            self::VERDE => 'success',
            self::AMARILLO => 'warning',
            self::ROJO => 'danger',
            default => 'gray',
        };
    }
}
