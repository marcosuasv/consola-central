<?php

namespace App\Servicios\Seguridad;

/**
 * Utilidades de direcciones IP.
 */
final class Ip
{
    /**
     * Pública = no es privada (10/8, 172.16/12, 192.168/16, fc00::/7) ni reservada (127/8, 0/8, 169.254/16, ::1...).
     */
    public static function esPublica(?string $ip): bool
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
