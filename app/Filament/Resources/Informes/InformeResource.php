<?php

namespace App\Filament\Resources\Informes;

use App\Filament\Resources\Informes\Pages\ListInformes;
use App\Filament\Resources\Informes\Pages\VerInforme;
use App\Models\Informe;
use App\Servicios\Monitoreo\Semaforo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Historial de informes diarios (solo lectura).
 */
class InformeResource extends Resource
{
    protected static ?string $model = Informe::class;

    protected static ?string $slug = 'informes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoreo';

    protected static ?string $modelLabel = 'informe';

    protected static ?string $pluralModelLabel = 'informes';

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Informe $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')->label('Generado')->dateTime('d/m/Y H:i')->sortable()
                    ->description(fn (Informe $r) => $r->completo ? null : 'Rápido (sin análisis de librerías)'),
                TextColumn::make('semaforo')->label('Estado general')->badge()
                    ->formatStateUsing(fn (string $state) => Semaforo::etiqueta($state))
                    ->color(fn (string $state) => Semaforo::colorFilament($state)),
                TextColumn::make('rojos')->label('Rojos')->badge()->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('amarillos')->label('Amarillos')->badge()->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('enviado_a')->label('Enviado a')
                    ->state(fn (Informe $r) => $r->error_envio ? 'Error de envío' : (count($r->enviado_a ?? []) ?: 'Nadie'))
                    ->color(fn (Informe $r) => $r->error_envio ? 'danger' : null)
                    ->tooltip(fn (Informe $r) => $r->error_envio ?: implode(', ', $r->enviado_a ?? [])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentChartBar)
            ->emptyStateHeading('Todavía no hay informes')
            ->emptyStateDescription('El primero se genera a las 07:00 cuando el timer consola-informe.timer esté instalado, o con «Generar informe ahora».');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInformes::route('/'),
            'view' => VerInforme::route('/{record}'),
        ];
    }
}
