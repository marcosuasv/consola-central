<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

class UsuarioForm
{
    public static function configure(Schema $schema): Schema
    {
        $esUnoMismo = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema->components([
            Section::make('Cuenta')
                ->icon(Heroicon::OutlinedIdentification)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(150)
                        ->unique(ignoreRecord: true)
                        ->endsWith([User::DOMINIO])
                        ->validationMessages(['ends_with' => 'Solo se permiten cuentas '.User::DOMINIO.'.'])
                        ->helperText('Solo cuentas '.User::DOMINIO.'.'),
                    TextInput::make('password')
                        ->label(fn (?User $record) => $record ? 'Nueva contraseña' : 'Contraseña')
                        ->password()
                        ->revealable()
                        ->required(fn (?User $record) => $record === null)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->rule(Password::min(12)->letters()->numbers())
                        ->helperText(fn (?User $record) => ($record ? 'Déjala vacía para no cambiarla. ' : '').'Mínimo 12 caracteres con letras y números.'),
                ]),

            Section::make('Acceso')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ToggleButtons::make('rol')
                        ->label('Rol')
                        ->options(User::ROLES)
                        ->icons([User::ROL_ADMIN => Heroicon::OutlinedWrenchScrewdriver, User::ROL_LECTURA => Heroicon::OutlinedEye])
                        ->colors([User::ROL_ADMIN => 'warning', User::ROL_LECTURA => 'info'])
                        ->default(User::ROL_LECTURA)
                        ->inline()
                        ->required()
                        ->disabled($esUnoMismo)
                        ->helperText('Administrador: puede encender, apagar y reiniciar (requiere verificación en dos pasos). Solo lectura: solo consulta.'),
                    Toggle::make('activo')
                        ->label('Cuenta activa')
                        ->default(true)
                        ->disabled($esUnoMismo)
                        ->helperText('Una cuenta inactiva no puede entrar. Las cuentas no se borran para conservar la auditoría.'),
                    TextEntry::make('mfa')
                        ->label('Verificación en dos pasos')
                        ->state(fn (?User $record) => $record?->tieneMfa() ? 'Activa' : 'No activada')
                        ->badge()
                        ->color(fn (?User $record) => $record?->tieneMfa() ? 'success' : 'danger')
                        ->helperText('Cada usuario la activa desde su perfil.')
                        ->visibleOn('edit'),
                ]),
        ]);
    }
}
