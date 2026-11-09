<?php

namespace App\Servicios\Informes;

use App\Mail\AvisoIncidente;
use App\Mail\InformeDiario;
use App\Models\Destinatario;
use App\Models\Incidente;
use App\Models\Informe;
use Illuminate\Support\Facades\Mail;

/**
 * Envío de correos de la consola. Un fallo de SMTP nunca rompe el recolector ni el informe: se registra.
 */
class ServicioCorreo
{
    /** ¿Hay un servidor de correo configurado? (con «log» o «array» no sale nada del servidor) */
    public static function configurado(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    /**
     * Envía el informe a los destinatarios a los que les toca según su nivel.
     *
     * @return array{enviados: list<string>, error: ?string}
     */
    public function enviarInforme(Informe $informe): array
    {
        $correos = Destinatario::where('activo', true)->get()
            ->filter(fn (Destinatario $d) => $d->recibeInforme($informe->semaforo))
            ->pluck('correo')->all();

        $resultado = $this->enviar($correos, fn () => new InformeDiario($informe));
        $informe->forceFill(['enviado_a' => $resultado['enviados'], 'error_envio' => $resultado['error']])->save();

        return $resultado;
    }

    /** @return array{enviados: list<string>, error: ?string} */
    public function enviarPrueba(Informe $informe, string $correo): array
    {
        return $this->enviar([$correo], fn () => new InformeDiario($informe, prueba: true));
    }

    /** @return array{enviados: list<string>, error: ?string} */
    public function avisarIncidente(Incidente $incidente, string $tipo): array
    {
        $correos = Destinatario::where('activo', true)->where('avisos_inmediatos', true)->pluck('correo')->all();

        return $this->enviar($correos, fn () => new AvisoIncidente($incidente, $tipo));
    }

    private function enviar(array $correos, callable $mensaje): array
    {
        $enviados = [];
        $error = null;

        foreach ($correos as $correo) {
            try {
                Mail::to($correo)->send($mensaje());
                $enviados[] = $correo;
            } catch (\Throwable $e) {
                report($e);
                $error = mb_substr($e->getMessage(), 0, 480);
            }
        }

        return ['enviados' => $enviados, 'error' => $error];
    }
}
