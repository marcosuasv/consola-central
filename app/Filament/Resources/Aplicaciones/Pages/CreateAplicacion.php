<?php

namespace App\Filament\Resources\Aplicaciones\Pages;

use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Filament\Resources\Aplicaciones\Schemas\AplicacionForm;
use Filament\Resources\Pages\CreateRecord;

class CreateAplicacion extends CreateRecord
{
    protected static string $resource = AplicacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AplicacionForm::limpiarDatos($data);
    }
}
