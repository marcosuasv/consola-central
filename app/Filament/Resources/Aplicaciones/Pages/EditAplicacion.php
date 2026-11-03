<?php

namespace App\Filament\Resources\Aplicaciones\Pages;

use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Filament\Resources\Aplicaciones\Schemas\AplicacionForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAplicacion extends EditRecord
{
    protected static string $resource = AplicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AplicacionForm::limpiarDatos($data);
    }
}
