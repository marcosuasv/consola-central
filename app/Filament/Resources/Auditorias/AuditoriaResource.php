<?php

namespace App\Filament\Resources\Auditorias;

use App\Filament\Resources\Auditorias\Pages\ListAuditorias;
use App\Filament\Resources\Auditorias\Tables\AuditoriasTable;
use App\Models\Auditoria;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Bitácora de acciones privilegiadas. Solo lectura: no se crea, edita ni borra desde la consola.
 */
class AuditoriaResource extends Resource
{
    protected static ?string $model = Auditoria::class;

    protected static ?string $slug = 'auditoria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Seguridad';

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?int $navigationSort = 10;

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

    public static function getNavigationBadge(): ?string
    {
        $errores = Auditoria::where('resultado', 'error')->where('created_at', '>=', now()->subDay())->count();

        return $errores > 0 ? (string) $errores : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Acciones con error en las últimas 24 h';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('user.name')->label('Usuario')->placeholder('Sistema')
                        ->helperText(fn (Auditoria $record) => $record->user?->email),
                    TextEntry::make('accion')->label('Acción')->badge(),
                    TextEntry::make('objetivo')->label('Objetivo')->fontFamily('mono'),
                    TextEntry::make('resultado')->label('Resultado')->badge()
                        ->color(fn (string $state) => $state === 'ok' ? 'success' : 'danger')
                        ->formatStateUsing(fn (string $state) => $state === 'ok' ? 'Correcto' : 'Error'),
                    TextEntry::make('codigo_salida')->label('Código de salida')->placeholder('—'),
                    TextEntry::make('ip')->label('IP')->placeholder('—'),
                    TextEntry::make('aplicacion.nombre')->label('Aplicación')->placeholder('—'),
                    TextEntry::make('parametros')->label('Comando')->columnSpanFull()->fontFamily('mono')
                        ->formatStateUsing(fn ($state, Auditoria $record) => 'sudo -n consola-ctl '.implode(' ', $record->parametros ?? [])),
                    TextEntry::make('salida')->label('Salida')->columnSpanFull()->fontFamily('mono')->placeholder('Sin salida')
                        ->extraAttributes(['style' => 'white-space: pre-wrap; max-height: 20rem; overflow: auto']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return AuditoriasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditorias::route('/'),
        ];
    }
}
