<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Auditoria;
use App\Servicios\Asistente\Paquete;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Comportamiento común de los asistentes: generar, mostrar, descargar y registrar el script.
 */
trait ManejaPaqueteAsistente
{
    public ?array $data = [];

    /** Paquete generado (archivos + script) para la vista previa. */
    public ?array $paquete = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->esAdmin();
    }

    abstract protected function construirPaquete(array $datos): Paquete;

    public function generar(): void
    {
        $datos = $this->form->getState();

        try {
            $paquete = $this->construirPaquete($datos);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title('Revisa los datos')->body($e->getMessage())->send();

            return;
        }

        $this->paquete = $paquete->aArreglo();

        Auditoria::create([
            'user_id' => auth()->id(),
            'accion' => 'asistente.generar',
            'objetivo' => $paquete->nombreScript,
            'parametros' => array_map(fn ($v) => is_scalar($v) || $v === null ? $v : null, $datos),
            'resultado' => 'ok',
            'salida' => mb_strcut($paquete->script, 0, Auditoria::MAX_SALIDA),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        Notification::make()->success()->title('Script generado')->body('Revísalo abajo y descárgalo para ejecutarlo como root.')->send();
        $this->dispatch('paquete-generado');
    }

    public function descargarAction(): Action
    {
        return Action::make('descargar')
            ->label('Descargar script')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn () => $this->paquete !== null)
            ->action(fn () => response()->streamDownload(
                function () {
                    echo $this->paquete['script'];
                },
                $this->paquete['nombre_script'],
                ['Content-Type' => 'text/x-shellscript; charset=UTF-8'],
            ));
    }

    public function reiniciarAction(): Action
    {
        return Action::make('reiniciar')
            ->label('Empezar de nuevo')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn () => $this->paquete !== null)
            ->action(function () {
                $this->paquete = null;
                $this->form->fill($this->valoresIniciales());
            });
    }

    protected function botonGenerar(): HtmlString
    {
        return new HtmlString(view('filament.partes.asistente-boton')->render());
    }

    abstract protected function valoresIniciales(): array;
}
