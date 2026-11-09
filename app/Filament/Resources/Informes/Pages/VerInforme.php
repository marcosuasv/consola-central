<?php

namespace App\Filament\Resources\Informes\Pages;

use App\Filament\Resources\Informes\InformeResource;
use App\Models\Informe;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Muestra el informe tal como se envió por correo.
 *
 * @property Informe $record
 */
class VerInforme extends Page
{
    use InteractsWithRecord;

    protected static string $resource = InformeResource::class;

    protected string $view = 'filament.pages.ver-informe';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Informe del '.$this->record->created_at->format('d/m/Y H:i');
    }

    public function getSubheading(): ?string
    {
        $enviados = $this->record->enviado_a ?? [];

        return $this->record->error_envio
            ? 'Error de envío: '.$this->record->error_envio
            : ($enviados ? 'Enviado a: '.implode(', ', $enviados) : 'No se envió por correo.');
    }
}
