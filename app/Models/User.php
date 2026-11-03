<?php

namespace App\Models;

use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    public const ROL_ADMIN = 'admin';

    public const ROL_LECTURA = 'lectura';

    public const ROLES = [
        self::ROL_ADMIN => 'Administrador',
        self::ROL_LECTURA => 'Solo lectura',
    ];

    public const DOMINIO = '@gbpo.com.mx';

    protected $attributes = [
        'rol' => self::ROL_LECTURA,
        'activo' => true,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Quién puede entrar: cuentas activas @gbpo.com.mx.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->activo && str_ends_with(strtolower($this->email), self::DOMINIO);
    }

    public function tieneMfa(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Puede usar los botones de control: admin activo con verificación en dos pasos
     * (se puede desactivar el requisito con CONSOLA_MFA_ADMIN=false, solo para emergencias).
     */
    public function puedeControlar(): bool
    {
        return $this->esAdmin() && ($this->tieneMfa() || ! config('consola.mfa_admin', true));
    }

    /** Solo los administradores ven los controles. */
    public function esAdmin(): bool
    {
        return $this->rol === self::ROL_ADMIN && $this->activo;
    }
}
