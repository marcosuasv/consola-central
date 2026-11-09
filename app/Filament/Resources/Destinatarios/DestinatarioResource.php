<?php

namespace App\Filament\Resources\Destinatarios;

use App\Filament\Resources\Destinatarios\Pages\ManageDestinatarios;
use App\Models\Destinatario;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «Avisar»: quién recibe el informe diario y los avisos inmediatos. Solo administradores.
 */
class DestinatarioResource extends Resource
{
    protected static ?string $model = Destinatario::class;

    protected static ?string $slug = 'avisar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Avisar';

    protected static ?string $modelLabel = 'destinatario';

    protected static ?string $pluralModelLabel = 'destinatarios';

    protected static ?int $navigationSort = 25;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->esAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(100),
            TextInput::make('correo')
                ->label('Correo')
                ->email()
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true)
                ->regex('/'.preg_quote(User::DOMINIO, '/').'$/i')
                ->dehydrateStateUsing(fn (?string $state) => strtolower(trim((string) $state)))
                ->validationMessages(['regex' => 'Solo se permiten correos '.User::DOMINIO.'.'])
                ->helperText('Solo correos '.User::DOMINIO.': el informe contiene datos internos del servidor.'),
            Select::make('nivel_minimo')
                ->label('Informe diario')
                ->options(Destinatario::NIVELES)
                ->default('todo')
                ->required()
                ->native(false),
            Toggle::make('avisos_inmediatos')
                ->label('Avisos inmediatos')
                ->default(true)
                ->helperText('Correo al momento cuando una app o el servidor lleva '.config('consola.correo.minutos_confirmacion').' minutos en rojo, y cuando se recupera.'),
            Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->description(fn (Destinatario $r) => $r->correo)->searchable(['nombre', 'correo']),
                TextColumn::make('nivel_minimo')->label('Informe diario')->badge()
                    ->formatStateUsing(fn (string $state) => Destinatario::NIVELES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'rojo' => 'danger', 'amarillo' => 'warning', default => 'info'
                    }),
                IconColumn::make('avisos_inmediatos')->label('Avisos inmediatos')->boolean(),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedEnvelope)
            ->emptyStateHeading('Nadie recibe correos todavía')
            ->emptyStateDescription('Agrega a quien deba recibir el informe de cada mañana y los avisos de fallas.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDestinatarios::route('/')];
    }
}
