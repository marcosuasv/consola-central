<?php

namespace App\Servicios\Asistente;

use App\Models\Aplicacion;
use InvalidArgumentException;

/**
 * Todo lo que entra a un script de root se valida aquí con expresiones estrictas.
 * Así ningún valor puede romper el bash, el SQL ni las configuraciones generadas.
 */
final class Validador
{
    public const REGEX_CLAVE = '/^[a-z0-9](?:[a-z0-9-]{0,40}[a-z0-9])?$/';

    public const REGEX_USUARIO_SO = '/^[a-z_][a-z0-9_-]{0,31}$/';

    public const REGEX_USUARIO_BD = '/^[A-Za-z0-9_]{1,32}$/';

    public const REGEX_NOMBRE = '/^[\p{L}\p{N} ()._-]{2,100}$/u';

    public const REGEX_RUTA_ETC = '#^/etc/[A-Za-z0-9._/*-]+$#';

    public const REGEX_CORREO = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';

    public const SERVIDORES_OCTANE = ['swoole', 'roadrunner', 'frankenphp'];

    public static function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw new InvalidArgumentException($mensaje);
        }
    }

    public static function texto(mixed $valor, string $regex, string $campo): string
    {
        $valor = trim((string) $valor);
        self::exigir((bool) preg_match($regex, $valor), "Valor inválido en «{$campo}»: {$valor}");

        return $valor;
    }

    public static function puerto(mixed $valor, string $campo, int $minimo = 1): int
    {
        self::exigir(is_numeric($valor) && (int) $valor >= $minimo && (int) $valor <= 65535, "Puerto inválido en «{$campo}».");

        return (int) $valor;
    }

    /** @return list<int> */
    public static function puertos(mixed $valor, string $campo): array
    {
        $texto = preg_replace('/\s+/', '', (string) $valor);
        self::exigir((bool) preg_match(Aplicacion::REGEX_PUERTOS, $texto), "Lista de puertos inválida en «{$campo}» (ej. 8100,4900).");

        return array_values(array_unique(array_map(fn ($p) => self::puerto($p, $campo), explode(',', $texto))));
    }

    public static function rutaEtc(mixed $valor, string $campo): string
    {
        $valor = trim((string) $valor);
        self::exigir(preg_match(self::REGEX_RUTA_ETC, $valor) && ! str_contains($valor, '..'), "Ruta inválida en «{$campo}» (debe estar en /etc).");

        return $valor;
    }

    /** Puertos ya usados por otras apps registradas (interno o públicos). */
    public static function puertosOcupados(?int $excepto = null): array
    {
        $ocupados = [];
        foreach (Aplicacion::when($excepto, fn ($q) => $q->whereKeyNot($excepto))->get() as $app) {
            foreach ([$app->puerto_interno, ...$app->listaPuertosPublicos()] as $p) {
                if ($p) {
                    $ocupados[$p] = $app->nombre;
                }
            }
        }

        return $ocupados;
    }

    /** Primer puerto interno libre del rango configurado. */
    public static function puertoInternoSugerido(): int
    {
        [$desde, $hasta] = config('consola.asistente.rango_puertos_internos', [18000, 18999]);
        $ocupados = self::puertosOcupados();
        for ($p = $desde + 100; $p <= $hasta; $p += 5) {
            if (! isset($ocupados[$p])) {
                return $p;
            }
        }

        return $desde;
    }
}
