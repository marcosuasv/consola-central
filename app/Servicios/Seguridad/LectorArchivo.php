<?php

namespace App\Servicios\Seguridad;

/**
 * Lee el final de un log de sistema con límite de bytes (sin cargar el archivo completo).
 * Solo rutas de /var/log permitidas.
 */
final class LectorArchivo
{
    private const PERMITIDAS = [
        '#^/var/log/nginx/[A-Za-z0-9._-]+\.log(\.1)?$#',
        '#^/var/log/fail2ban\.log(\.1)?$#',
        '#^/var/log/consola-ctl\.log(\.1)?$#',
    ];

    /** @return list<string> */
    public static function final(?string $ruta, int $maxBytes): array
    {
        if ($ruta === null || ! self::permitida($ruta) || ! is_file($ruta) || ! is_readable($ruta)) {
            return [];
        }

        $manejador = @fopen($ruta, 'rb');
        if ($manejador === false) {
            return [];
        }

        try {
            $tamano = fstat($manejador)['size'] ?? 0;
            $inicio = max(0, $tamano - $maxBytes);
            fseek($manejador, $inicio);
            $contenido = $tamano > 0 ? (string) fread($manejador, $tamano - $inicio) : '';
        } finally {
            fclose($manejador);
        }

        $lineas = preg_split('/\R/', rtrim($contenido, "\r\n"));
        if ($inicio > 0) {
            array_shift($lineas); // primera línea cortada
        }

        return array_values(array_filter($lineas, fn ($l) => $l !== ''));
    }

    public static function permitida(string $ruta): bool
    {
        if (str_contains($ruta, '..') || str_contains($ruta, "\0")) {
            return false;
        }
        if (app()->runningUnitTests() && str_starts_with($ruta, storage_path().'/')) {
            return true;
        }
        foreach (self::PERMITIDAS as $regex) {
            if (preg_match($regex, $ruta)) {
                return true;
            }
        }

        return false;
    }
}
