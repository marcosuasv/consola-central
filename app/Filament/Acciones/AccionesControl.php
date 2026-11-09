<?php

namespace App\Filament\Acciones;

use App\Models\Aplicacion;
use App\Servicios\Control\ControlAplicacion;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

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
     * @param  Closure(): ?Aplicacion  $aplicacion
     */
    public static function fija(string $accion, Closure $aplicacion, ?string $nombre = null): Action
    {
        // Sin aplicación fija (closure que regresa null) se usa el registro de la fila de la tabla
        return self::construir($nombre ?? $accion, fn () => $accion, fn (array $arguments = [], ?Model $record = null) => $aplicacion() ?? $record);
    }

    /**
     * Acción genérica que recibe ['aplicacion' => id, 'accion' => ...] como argumentos (tarjetas del tablero).
     */
    public static function porArgumentos(string $nombre = 'controlar'): Action
    {
        return self::construir(
            $nombre,
            fn (array $arguments, ?Model $record = null): string => array_key_exists($arguments['accion'] ?? '', self::DISENO) ? $arguments['accion'] : ControlAplicacion::RECARGAR,
            fn (array $arguments, ?Model $record = null): ?Aplicacion => Aplicacion::find((int) ($arguments['aplicacion'] ?? 0)),
        )->iconButton()->size('sm');
    }

    /**
     * Reintentar un job fallido. Argumentos: ['job' => uuid|id, 'nombre' => clase del job].
     *
     * @param  Closure(array): ?Aplicacion  $aplicacion
     */
    public static function reintentarJob(Closure $aplicacion, string $nombre = 'reintentarJob'): Action
    {
        return self::accionCola($nombre, $aplicacion, todos: false)
            ->label('Reintentar')
            ->iconButton()
            ->size('sm')
            ->modalHeading(fn (array $arguments, ?Model $record = null) => 'Reintentar '.($arguments['nombre'] ?? 'job'))
            ->modalDescription('El job vuelve a la cola y el worker lo procesa de nuevo. Si la causa del error sigue, volverá a fallar.');
    }

    /**
     * Reintentar todos los jobs fallidos de la app. Argumento opcional: ['aplicacion' => id].
     *
     * @param  Closure(array): ?Aplicacion  $aplicacion
     */
    public static function reintentarTodos(Closure $aplicacion, string $nombre = 'reintentarTodos'): Action
    {
        return self::accionCola($nombre, $aplicacion, todos: true)
            ->label('Reintentar todos')
            ->modalHeading(fn (array $arguments, ?Model $record = null) => 'Reintentar todos los jobs fallidos de '.($aplicacion($arguments, $record)?->nombre ?? ''))
            ->modalDescription('Todos los jobs fallidos vuelven a la cola. Úsalo cuando ya se corrigió la causa (por ejemplo, un servicio externo que estuvo caído).');
    }

    private static function accionCola(string $nombre, Closure $aplicacion, bool $todos): Action
    {
        return Action::make($nombre)
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color($todos ? 'warning' : 'gray')
            ->tooltip(fn () => auth()->user()?->puedeControlar()
                ? ($todos ? 'Reintentar todos los fallidos' : 'Reintentar este job')
                : 'Activa la verificación en dos pasos en tu perfil para usar los controles.')
            ->visible(fn (array $arguments, ?Model $record = null) => auth()->user()?->esAdmin() && $aplicacion($arguments, $record) !== null)
            ->disabled(fn () => ! auth()->user()?->puedeControlar())
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel('Sí, reintentar')
            ->rateLimit(20)
            ->action(function (array $arguments, ?Model $record = null) use ($aplicacion, $todos): void {
                $app = $aplicacion($arguments, $record);
                $job = $todos ? 'all' : (string) ($arguments['job'] ?? '');

                if (! $app || ! auth()->user()?->puedeControlar()) {
                    Notification::make()->danger()->title('No autorizado')->send();

                    return;
                }

                try {
                    $resultado = app(ControlAplicacion::class)->reintentar($app, $job);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('No se pudo reintentar')->body($e->getMessage())->send();

                    return;
                }

                $resultado->ok
                    ? Notification::make()->success()->title($todos ? 'Jobs fallidos enviados de nuevo a la cola' : 'Job enviado de nuevo a la cola')
                        ->body('Quedó registrado en la auditoría.')->send()
                    : Notification::make()->danger()->persistent()->title('El reintento falló')->body($resultado->resumen())->send();
            });
    }

    private static function construir(string $nombre, Closure $accion, Closure $aplicacion): Action
    {
        $d = fn (array $arguments, string $clave) => self::DISENO[$accion($arguments)][$clave];

        return Action::make($nombre)
            ->label(fn (array $arguments, ?Model $record = null) => ControlAplicacion::ETIQUETAS[$accion($arguments)])
            ->tooltip(fn (array $arguments, ?Model $record = null) => auth()->user()?->puedeControlar()
                ? ControlAplicacion::ETIQUETAS[$accion($arguments)]
                : 'Activa la verificación en dos pasos en tu perfil para usar los controles.')
            ->icon(fn (array $arguments, ?Model $record = null) => $d($arguments, 'icono'))
            ->color(fn (array $arguments, ?Model $record = null) => $d($arguments, 'color'))
            ->visible(function (array $arguments, ?Model $record = null) use ($accion, $aplicacion): bool {
                $app = $aplicacion($arguments, $record);

                return auth()->user()?->esAdmin()
                    && $app !== null
                    && in_array($accion($arguments), ControlAplicacion::disponibles($app), true);
            })
            ->disabled(fn () => ! auth()->user()?->puedeControlar())
            ->requiresConfirmation()
            ->modalIcon(fn (array $arguments, ?Model $record = null) => $d($arguments, 'icono'))
            ->modalHeading(fn (array $arguments, ?Model $record = null) => ControlAplicacion::ETIQUETAS[$accion($arguments)].' '.($aplicacion($arguments, $record)?->nombre ?? ''))
            ->modalDescription(fn (array $arguments, ?Model $record = null) => $d($arguments, 'texto'))
            ->modalSubmitActionLabel(fn (array $arguments, ?Model $record = null) => $d($arguments, 'confirmar'))
            ->schema(function (array $arguments, ?Model $record = null) use ($accion, $aplicacion): array {
                $app = $aplicacion($arguments, $record);
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
            ->action(function (array $arguments, ?Model $record = null) use ($accion, $aplicacion): void {
                $app = $aplicacion($arguments, $record);
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
