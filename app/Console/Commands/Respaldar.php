<?php

namespace App\Console\Commands;

use App\Servicios\Respaldos\ServicioRespaldos;
use App\Support\Formato;
use Illuminate\Console\Command;

/**
 * Paso 14: respalda las bases de las apps activas. Lo ejecuta cada noche un timer de systemd
 * como usuario `consola` (ver docs/systemd/consola-respaldos.*).
 */
class Respaldar extends Command
{
    protected $signature = 'consola:respaldar {--base= : Solo esta base de datos}';

    protected $description = 'Respalda las bases de datos de las aplicaciones activas';

    public function handle(ServicioRespaldos $servicio): int
    {
        $objetivos = collect($servicio->objetivos())
            ->when($this->option('base'), fn ($c, $base) => $c->where('base', $base));

        if ($objetivos->isEmpty()) {
            $this->warn('No hay bases para respaldar.');

            return self::SUCCESS;
        }

        $fallas = 0;
        foreach ($objetivos as $o) {
            $r = $servicio->respaldar($o['aplicacion'], $o['base'], $o['dias']);
            $r->exitoso()
                ? $this->line(sprintf('%-22s ok     %8s  %s', $o['nombre'], Formato::bytes($r->tamano_bytes, 1), $r->archivo))
                : $this->error(sprintf('%-22s ERROR  %s', $o['nombre'], mb_strimwidth((string) $r->mensaje, 0, 160, '…')));
            $fallas += $r->exitoso() ? 0 : 1;
        }

        return $fallas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
