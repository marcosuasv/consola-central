<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Quién puede entrar a la Consola Central.
     * Por ahora: solo cuentas @gbpo.com.mx (en el Paso 7 se agregan roles y 2FA).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with(strtolower($this->email), '@gbpo.com.mx');
    }
}
