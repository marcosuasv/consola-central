<?php

namespace App\Filament\Resources\Auditorias\Tables;

use App\Models\Auditoria;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn (Auditoria $r) => $r->created_at?->locale('es')->diffForHumans())
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->description(fn (Auditoria $r) => $r->user?->email)
                    ->searchable(),
                TextColumn::make('accion')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        str_ends_with($state, 'stop') => 'danger',
                        str_ends_with($state, 'restart') => 'warning',
                        str_ends_with($state, 'start') => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('objetivo')
                    ->label('Objetivo')
                    ->fontFamily('mono')
                    ->description(fn (Auditoria $r) => $r->aplicacion?->nombre)
                    ->searchable(),
                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->icon(fn (string $state) => $state === 'ok' ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                    ->color(fn (string $state) => $state === 'ok' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'ok' ? 'Correcto' : 'Error'),
                TextColumn::make('salida')
                    ->label('Salida')
                    ->limit(60)
                    ->fontFamily('mono')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('resultado')
                    ->label('Resultado')
                    ->options(['ok' => 'Correcto', 'error' => 'Error']),
                SelectFilter::make('aplicacion_id')
                    ->label('Aplicación')
                    ->relationship('aplicacion', 'nombre'),
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name'),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['desde'] ?? null, fn ($q, $f) => $q->whereDate('created_at', '>=', $f))
                        ->when($data['hasta'] ?? null, fn ($q, $f) => $q->whereDate('created_at', '<=', $f))),
            ])
            ->recordActions([
                ViewAction::make()->modalWidth('3xl'),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading('Sin acciones registradas')
            ->emptyStateDescription('Aquí aparecerá cada encendido, apagado o reinicio hecho desde la consola.');
    }
}
