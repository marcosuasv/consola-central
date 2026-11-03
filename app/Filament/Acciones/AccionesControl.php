<?php

namespace App\Filament\Acciones;

use App\Models\Aplicacion;
use App\Servicios\Control\ControlAplicacion;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Botones de control de aplicaciones (Encender, Apagar, Reiniciar, Recargar, Reiniciar cola).
 *
 * - Visibles solo para administradores; deshabilitados si el admin no tiene verificación en dos pasos.
 * - Confirmación en ventana; Apagar/Reiniciar de producción exige escribir el nombre de la app.
 * - Todo pasa por ControlAplicacion → ConsolaCtl → auditoría.
 */
final class AccionesControl
{
    private const DISENO = [
        ControlAplicacion::ENCENDER => [
            'icono' => Heroicon::OutlinedPlay, 'color' => 'success', 'confirmar' => 'Sí, encender',
            'texto' => 'Se encenderá el servicio de la app y, si existen, el worker de la cola y el scheduler.',
        ],
        ControlAplicacion::APAGAR => [
            'icono' => Heroicon::OutlinedStop, 'color' => 'danger', 'confirmar' => 'Sí, apagar',
            'texto' => 'La app dejará de responder para todos los usuarios. También se detienen la cola y el scheduler.',
        ],
        ControlAplicacion::REINICIAR => [
            'icono' => Heroicon::OutlinedArrowPath, 'color' => 'warning', 'confirmar' => 'Sí, reiniciar',
            'texto' => 'La app dejará de responder unos segundos mientras arranca de nuevo, junto con la cola y el scheduler.',
        ],
        ControlAplicacion::RECARGAR => [
            'icono' => Heroicon::OutlinedArrowPathRoundedSquare, 'color' => 'info', 'confirmar' => 'Sí, recargar',
            'texto' => 'Recarga el código sin cortar las peticiones en curso (Octane: reload; FPM: recarga de PHP-FPM).',
        ],
        ControlAplicacion::REINICIAR_COLA => [
            'icono' => Heroicon::OutlinedQueueList, 'color' => 'gray', 'confirmar' => 'Sí, reiniciar workers',
            'texto' => 'Los workers terminan el job actual y vuelven a arrancar con el código nuevo (queue:restart).',
        ],
    ];

    /**
     * Acción con la operación fija (para la cabecera del monitor).
     *
     * @param  Closure(): Aplicacion  $aplicacion
     */
    public static function fija(string $accion, Closure $aplicacion): Action
    {
        return self::construir($accion, fn () => $accion, fn () => $aplicacion());
    }

    /**
     * Acción genérica que recibe ['aplicacion' => id, 'accion' => ...] como argumentos (tarjetas del tablero).
     */
    public static function porArgumentos(string $nombre = 'controlar'): Action
    {
        return self::construir(
            $nombre,
            fn (array $arguments): string => array_key_exists($arguments['accion'] ?? '', self::DISENO) ? $arguments['accion'] : ControlAplicacion::RECARGAR,
            fn (array $arguments): ?Aplicacion => Aplicacion::find((int) ($arguments['aplicacion'] ?? 0)),
        )->iconButton()->size('sm');
    }

    private static function construir(string $nombre, Closure $accion, Closure $aplicacion): Action
    {
        $d = fn (array $arguments, string $clave) => self::DISENO[$accion($arguments)][$clave];

        return Action::make($nombre)
            ->label(fn (array $arguments) => ControlAplicacion::ETIQUETAS[$accion($arguments)])
            ->tooltip(fn (array $arguments) => auth()->user()?->puedeControlar()
                ? ControlAplicacion::ETIQUETAS[$accion($arguments)]
                : 'Activa la verificación en dos pasos en tu perfil para usar los controles.')
            ->icon(fn (array $arguments) => $d($arguments, 'icono'))
            ->color(fn (array $arguments) => $d($arguments, 'color'))
            ->visible(function (array $arguments) use ($accion, $aplicacion): bool {
                $app = $aplicacion($arguments);

                return auth()->user()?->esAdmin()
                    && $app !== null
                    && in_array($accion($arguments), ControlAplicacion::disponibles($app), true);
            })
            ->disabled(fn () => ! auth()->user()?->puedeControlar())
            ->requiresConfirmation()
            ->modalIcon(fn (array $arguments) => $d($arguments, 'icono'))
            ->modalHeading(fn (array $arguments) => ControlAplicacion::ETIQUETAS[$accion($arguments)].' '.($aplicacion($arguments)?->nombre ?? ''))
            ->modalDescription(fn (array $arguments) => $d($arguments, 'texto'))
            ->modalSubmitActionLabel(fn (array $arguments) => $d($arguments, 'confirmar'))
            ->schema(function (array $arguments) use ($accion, $aplicacion): array {
                $app = $aplicacion($arguments);
                if (! $app || ! ControlAplicacion::requiereNombre($app, $accion($arguments))) {
                    return [];
                }

                return [
                    TextInput::make('confirmacion')
                        ->label("Escribe «{$app->nombre}» para confirmar")
                        ->helperText('Es una app de producción: esta acción afecta a los usuarios.')
                        ->required()
                        ->autocomplete(false)
                        ->in([$app->nombre])
                        ->validationMessages(['in' => 'El nombre no coincide.']),
                ];
            })
            ->rateLimit(10)
            ->action(function (array $arguments) use ($accion, $aplicacion): void {
                $app = $aplicacion($arguments);
                $operacion = $accion($arguments);

                if (! $app || ! auth()->user()?->puedeControlar()) {
                    Notification::make()->danger()->title('No autorizado')->send();

                    return;
                }

                try {
                    $resultados = app(ControlAplicacion::class)->ejecutar($app, $operacion);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('No se pudo ejecutar la acción')->body($e->getMessage())->send();

                    return;
                }

                $fallidos = array_filter($resultados, fn ($r) => ! $r->ok);
                $etiqueta = ControlAplicacion::ETIQUETAS[$operacion];

                if ($fallidos === []) {
                    Notification::make()->success()
                        ->title("{$etiqueta}: {$app->nombre}")
                        ->body('Listo. La acción quedó registrada en la auditoría.')
                        ->send();
                } else {
                    Notification::make()->danger()->persistent()
                        ->title("{$etiqueta} falló en {$app->nombre}")
                        ->body(reset($fallidos)->resumen())
                        ->send();
                }
            });
    }
}
