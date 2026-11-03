<?php

namespace App\Console\Commands;

use App\Models\Aplicacion;
use App\Servicios\Monitoreo\Recolector;
use Illuminate\Console\Command;

/**
 * Toma una lectura de todas las apps y del sistema. Lo ejecuta cada minuto un timer de systemd
 * como usuario `consola` (lo instala el administrador).
 */
class Recolectar extends Command
{
    protected $signature = 'consola:recolectar {--app= : Solo la app con esta carpeta}';

    protected $description = 'Recolecta el estado de las aplicaciones y del servidor';

    public function handle(Recolector $recolector): int
    {
        $apps = Aplicacion::query()
            ->when($this->option('app'), fn ($q, $carpeta) => $q->where('carpeta', $carpeta))
            ->ordenadas()
            ->get();

        $fallas = 0;

        foreach ($apps as $app) {
            try {
                $estado = $recolector->recolectarApp($app);
                $this->line(sprintf('%-22s %-9s %s', $app->nombre, $estado->semaforo, implode(' | ', array_column($estado->alertas ?? [], 'mensaje'))));
            } catch (\Throwable $e) {
                $fallas++;
                report($e);
                $this->error("{$app->nombre}: {$e->getMessage()}");
            }
        }

        if (! $this->option('app')) {
            try {
                $sistema = $recolector->recolectarSistema();
                $this->line(sprintf('%-22s %-9s %s', 'Servidor', $sistema->semaforo, implode(' | ', array_column($sistema->alertas ?? [], 'mensaje'))));
            } catch (\Throwable $e) {
                $fallas++;
                report($e);
                $this->error("Servidor: {$e->getMessage()}");
            }

            $borrados = $recolector->purgar();
            if ($borrados > 0) {
                $this->line("Registros antiguos borrados: {$borrados}");
            }
        }

        return $fallas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
