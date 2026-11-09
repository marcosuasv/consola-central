<?php

namespace App\Console\Commands;

use App\Servicios\Informes\GeneradorInforme;
use App\Servicios\Informes\ServicioCorreo;
use Illuminate\Console\Command;

/**
 * Paso 12: genera el informe diario y lo envía a los destinatarios de «Avisar».
 * Lo ejecuta a las 07:00 el timer consola-informe.timer como usuario `consola`.
 */
class InformeDiario extends Command
{
    protected $signature = 'consola:informe-diario
        {--sin-enviar : Solo genera y guarda el informe}
        {--rapido : Sin composer audit/outdated}';

    protected $description = 'Genera y envía el informe diario de la Consola Central';

    public function handle(GeneradorInforme $generador, ServicioCorreo $correo): int
    {
        $informe = $generador->generar(completo: ! $this->option('rapido'));
        $this->line("Informe #{$informe->id}: {$informe->asunto()}");

        if ($this->option('sin-enviar')) {
            return self::SUCCESS;
        }

        if (! ServicioCorreo::configurado()) {
            $this->warn('El correo no está configurado (MAIL_MAILER='.config('mail.default').'): el informe queda solo en la consola y en el log.');
        }

        $r = $correo->enviarInforme($informe);
        $this->line('Enviado a: '.($r['enviados'] ? implode(', ', $r['enviados']) : 'nadie (sin destinatarios para este nivel)'));

        if ($r['error']) {
            $this->error('Error de envío: '.$r['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
