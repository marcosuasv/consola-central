<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Formatos legibles para la interfaz (en español).
 */
final class Formato
{
    public static function bytes(?int $bytes, int $decimales = 0): string
    {
        if ($bytes === null) {
            return '—';
        }

        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $valor = (float) $bytes;
        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return number_format($valor, $i >= 3 ? max(1, $decimales) : $decimales).' '.$unidades[$i];
    }

    /** "3 d 4 h", "5 h 12 min", "8 min" */
    public static function duracion(?CarbonInterface $desde): string
    {
        if ($desde === null) {
            return '—';
        }

        $segundos = max(0, now()->getTimestamp() - $desde->getTimestamp());
        $d = intdiv($segundos, 86400);
        $h = intdiv($segundos % 86400, 3600);
        $m = intdiv($segundos % 3600, 60);

        return match (true) {
            $d > 0 => "{$d} d {$h} h",
            $h > 0 => "{$h} h {$m} min",
            default => "{$m} min",
        };
    }

    public static function milisegundos(?int $ms): string
    {
        if ($ms === null) {
            return '—';
        }

        return $ms >= 1000 ? number_format($ms / 1000, 1).' s' : $ms.' ms';
    }

    public static function numero(?int $n): string
    {
        return $n === null ? '—' : number_format($n);
    }

    public static function hace(?CarbonInterface $fecha): string
    {
        return $fecha ? $fecha->locale('es')->diffForHumans() : 'nunca';
    }

    /**
     * Trazo SVG (atributo d) de una serie para un sparkline de $ancho x $alto.
     *
     * @param  list<int|float|null>  $valores
     */
    public static function sparkline(array $valores, int $ancho = 240, int $alto = 48): ?string
    {
        $limpios = array_values(array_filter($valores, fn ($v) => $v !== null));
        if (count($limpios) < 2) {
            return null;
        }

        $max = max($limpios);
        $min = min(0, min($limpios));
        $rango = ($max - $min) ?: 1;
        $n = count($valores);
        $puntos = [];

        foreach ($valores as $i => $v) {
            if ($v === null) {
                continue;
            }
            $x = round($i * $ancho / max(1, $n - 1), 1);
            $y = round($alto - 2 - (($v - $min) / $rango) * ($alto - 4), 1);
            $puntos[] = ($puntos === [] ? 'M' : 'L').$x.' '.$y;
        }

        return implode(' ', $puntos);
    }
}
