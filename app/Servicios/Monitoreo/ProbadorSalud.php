<?php

namespace App\Servicios\Monitoreo;

use App\Models\Aplicacion;
use Illuminate\Support\Facades\Http;

/**
 * Prueba de salud HTTP a la URL local de la app. 2xx/3xx = OK.
 */
class ProbadorSalud
{
    /**
     * @return array{codigo: ?int, latencia_ms: ?int, error: ?string}
     */
    public function probar(?string $url): array
    {
        if (blank($url) || ! preg_match(Aplicacion::REGEX_URL_SALUD, $url)) {
            return ['codigo' => null, 'latencia_ms' => null, 'error' => 'Sin URL de salud local'];
        }

        $inicio = hrtime(true);

        try {
            $respuesta = Http::withoutVerifying()      // certificados autofirmados en el servidor
                ->withoutRedirecting()                 // un 3xx ya cuenta como OK
                ->connectTimeout(3)
                ->timeout(config('consola.limites.timeout_http', 5))
                ->withUserAgent('ConsolaCentral/1.0 (salud)')
                ->get($url);

            return [
                'codigo' => $respuesta->status(),
                'latencia_ms' => (int) round((hrtime(true) - $inicio) / 1e6),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'codigo' => null,
                'latencia_ms' => null,
                'error' => mb_substr($e->getMessage(), 0, 250),
            ];
        }
    }

    public static function codigoOk(?int $codigo): bool
    {
        return $codigo !== null && $codigo >= 200 && $codigo < 400;
    }
}
