<?php

namespace App\Servicios\Control;

use App\Models\Aplicacion;
use App\Servicios\Monitoreo\Recolector;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Acciones de control por aplicación (Encender, Apagar, Reiniciar, Recargar, Reiniciar cola).
 * Solo administradores. Después de cada acción se toma una lectura nueva de la app.
 */
class ControlAplicacion
{
    public const ENCENDER = 'encender';

    public const APAGAR = 'apagar';

    public const REINICIAR = 'reiniciar';

    public const RECARGAR = 'recargar';

    public const REINICIAR_COLA = 'reiniciar_cola';

    public const ETIQUETAS = [
        self::ENCENDER => 'Encender',
        self::APAGAR => 'Apagar',
        self::REINICIAR => 'Reiniciar',
        self::RECARGAR => 'Recargar',
        self::REINICIAR_COLA => 'Reiniciar cola',
    ];

    public function __construct(private ConsolaCtl $ctl, private Recolector $recolector) {}

    /** Acciones que aplican a la app según su tipo. */
    public static function disponibles(Aplicacion $app): array
    {
        if ($app->esFpm()) {
            return [self::RECARGAR, self::REINICIAR_COLA];
        }

        return array_values(array_filter(
            [self::ENCENDER, self::APAGAR, self::REINICIAR, self::RECARGAR, self::REINICIAR_COLA],
            fn ($a) => $a !== self::REINICIAR_COLA || filled($app->servicio_cola),
        ));
    }

    /** Apagar/Reiniciar una app de producción exige escribir su nombre. */
    public static function requiereNombre(Aplicacion $app, string $accion): bool
    {
        return ! $app->es_pruebas && in_array($accion, [self::APAGAR, self::REINICIAR], true);
    }

    /**
     * @return list<ResultadoCtl>
     */
    public function ejecutar(Aplicacion $app, string $accion): array
    {
        if (! auth()->user()?->esAdmin()) {
            throw new AuthorizationException('Solo los administradores pueden controlar aplicaciones.');
        }

        if (! in_array($accion, self::disponibles($app), true)) {
            throw new \InvalidArgumentException("La acción {$accion} no aplica a {$app->nombre}.");
        }

        $ctl = $this->ctl->para($app);

        $resultados = match ($accion) {
            self::ENCENDER => $this->encender($ctl, $app),
            self::APAGAR => [$ctl->servicio('stop', $app->servicio)],       // PartOf: también cola y scheduler
            self::REINICIAR => [$ctl->servicio('restart', $app->servicio)], // PartOf: también cola y scheduler
            self::RECARGAR => [$app->esFpm() ? $ctl->fpmReload() : $ctl->servicio('reload', $app->servicio)],
            self::REINICIAR_COLA => [$ctl->colaReiniciar($app->carpeta)],
        };

        try {
            $this->recolector->recolectarApp($app);
        } catch (\Throwable $e) {
            report($e);
        }

        return $resultados;
    }

    /** start no se propaga por PartOf: se encienden app, cola y scheduler. */
    private function encender(ConsolaCtl $ctl, Aplicacion $app): array
    {
        $resultados = [$ctl->servicio('start', $app->servicio)];

        if ($resultados[0]->ok) {
            foreach ([$app->servicio_cola, $app->timer_scheduler] as $unidad) {
                if (filled($unidad)) {
                    $resultados[] = $ctl->servicio('start', $unidad);
                }
            }
        }

        return $resultados;
    }
}
