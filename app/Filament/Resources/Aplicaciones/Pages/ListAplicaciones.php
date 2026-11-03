<?php

namespace App\Filament\Resources\Aplicaciones\Pages;

use App\Filament\Resources\Aplicaciones\AplicacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAplicaciones extends ListRecords
{
    protected static string $resource = AplicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
