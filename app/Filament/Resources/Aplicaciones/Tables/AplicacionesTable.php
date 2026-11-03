<?php

namespace App\Filament\Resources\Aplicaciones\Tables;

use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Models\Aplicacion;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AplicacionesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->description(fn (Aplicacion $record): string => $record->carpeta)
                    ->searchable(['nombre', 'carpeta'])
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Aplicacion::TIPO_FPM ? 'FPM' : 'Octane')
                    ->color(fn (string $state): string => $state === Aplicacion::TIPO_FPM ? 'info' : 'primary'),
                TextColumn::make('servicio')
                    ->label('Servicio / pool')
                    ->state(fn (Aplicacion $record): ?string => $record->esFpm() ? 'pool '.$record->pool_fpm : $record->servicio)
                    ->fontFamily('mono'),
                TextColumn::make('puerto_interno')
                    ->label('Interno')
                    ->placeholder('—'),
                TextColumn::make('puertos_publicos')
                    ->label('Públicos')
                    ->placeholder('—'),
                TextColumn::make('base_datos')
                    ->label('Base de datos')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('es_pruebas')
                    ->label('Pruebas')
                    ->boolean(),
                IconColumn::make('publica_internet')
                    ->label('Internet')
                    ->boolean()
                    ->trueColor('warning'),
                IconColumn::make('monitorear')
                    ->label('Monitoreo')
                    ->boolean(),
                TextColumn::make('orden')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(Aplicacion::TIPOS),
                TernaryFilter::make('es_pruebas')
                    ->label('Pruebas'),
                TernaryFilter::make('monitorear')
                    ->label('Monitoreo'),
            ])
            ->recordActions([
                Action::make('monitor')
                    ->label('Monitor')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('gray')
                    ->url(fn (Aplicacion $record): string => AplicacionResource::getUrl('monitor', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
