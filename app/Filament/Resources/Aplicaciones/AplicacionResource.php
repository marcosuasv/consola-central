<?php

namespace App\Filament\Resources\Aplicaciones;

use App\Filament\Resources\Aplicaciones\Pages\CreateAplicacion;
use App\Filament\Resources\Aplicaciones\Pages\EditAplicacion;
use App\Filament\Resources\Aplicaciones\Pages\ListAplicaciones;
use App\Filament\Resources\Aplicaciones\Pages\MonitorAplicacion;
use App\Filament\Resources\Aplicaciones\Schemas\AplicacionForm;
use App\Filament\Resources\Aplicaciones\Tables\AplicacionesTable;
use App\Models\Aplicacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AplicacionResource extends Resource
{
    protected static ?string $model = Aplicacion::class;

    protected static ?string $slug = 'aplicaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $modelLabel = 'aplicación';

    protected static ?string $pluralModelLabel = 'aplicaciones';

    protected static ?string $navigationLabel = 'Aplicaciones';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return AplicacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AplicacionesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAplicaciones::route('/'),
            'create' => CreateAplicacion::route('/create'),
            'edit' => EditAplicacion::route('/{record}/edit'),
            'monitor' => MonitorAplicacion::route('/{record}/monitor'),
        ];
    }
}
