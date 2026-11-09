<?php

namespace App\Filament\Resources\Destinatarios\Pages;

use App\Filament\Resources\Destinatarios\DestinatarioResource;
use App\Servicios\Informes\ServicioCorreo;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDestinatarios extends ManageRecords
{
    protected static string $resource = DestinatarioResource::class;

    public function getSubheading(): ?string
    {
        return ServicioCorreo::configurado()
            ? 'Quién recibe el informe de las 07:00 y los avisos inmediatos de fallas.'
            : 'El correo aún no está configurado en el servidor (MAIL_MAILER='.config('mail.default').'): los mensajes no salen, solo quedan en el log. Ver docs/INFORME_DIARIO.md.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Agregar destinatario')];
    }
}
