<?php

namespace App\Filament\Resources\Usuarios\Tables;

use App\Models\Auditoria;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsuariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (User $r) => $r->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => User::ROLES[$state] ?? $state)
                    ->icon(fn (string $state) => $state === User::ROL_ADMIN ? Heroicon::OutlinedWrenchScrewdriver : Heroicon::OutlinedEye)
                    ->color(fn (string $state) => $state === User::ROL_ADMIN ? 'warning' : 'info'),
                IconColumn::make('mfa')
                    ->label('2 pasos')
                    ->state(fn (User $r) => $r->tieneMfa())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedShieldCheck)
                    ->falseIcon(Heroicon::OutlinedShieldExclamation)
                    ->falseColor(fn (User $r) => $r->esAdmin() ? 'danger' : 'gray'),
                IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean(),
                TextColumn::make('ultimo_acceso')
                    ->label('Último acceso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rol')->label('Rol')->options(User::ROLES),
                TernaryFilter::make('activo')->label('Activa'),
            ])
            ->recordActions([
                Action::make('quitarMfa')
                    ->label('Quitar 2 pasos')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('danger')
                    ->visible(fn (User $r) => $r->tieneMfa() && auth()->user()?->puedeControlar())
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $r) => "Quitar la verificación en dos pasos de {$r->name}")
                    ->modalDescription('Úsalo solo si la persona perdió su teléfono. Deberá activarla de nuevo; si es administrador, no podrá usar los controles hasta hacerlo.')
                    ->action(function (User $r) {
                        $r->saveAppAuthenticationSecret(null);
                        $r->saveAppAuthenticationRecoveryCodes(null);

                        Auditoria::create([
                            'user_id' => auth()->id(),
                            'accion' => 'usuario.quitar_mfa',
                            'objetivo' => $r->email,
                            'resultado' => 'ok',
                            'ip' => request()->ip(),
                            'created_at' => now(),
                        ]);

                        Notification::make()->success()->title('Verificación en dos pasos quitada')->send();
                    }),
                EditAction::make(),
            ]);
    }
}
