<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crea o actualiza una cuenta desde la terminal (útil para el primer administrador o si nadie puede entrar).
 */
class Usuario extends Command
{
    protected $signature = 'consola:usuario
        {email : Correo @gbpo.com.mx}
        {--nombre= : Nombre visible}
        {--rol=lectura : admin | lectura}
        {--desactivar : Desactiva la cuenta}
        {--contrasena : Pide una contraseña nueva}
        {--quitar-mfa : Quita la verificación en dos pasos (teléfono perdido)}';

    protected $description = 'Crea o actualiza una cuenta de la Consola Central';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $rol = (string) $this->option('rol');

        if (! str_ends_with($email, User::DOMINIO) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Solo se permiten cuentas '.User::DOMINIO);

            return self::FAILURE;
        }
        if (! array_key_exists($rol, User::ROLES)) {
            $this->error('Rol inválido. Usa: '.implode(', ', array_keys(User::ROLES)));

            return self::FAILURE;
        }

        $usuario = User::firstOrNew(['email' => $email]);
        $nuevo = ! $usuario->exists;

        $usuario->name = $this->option('nombre') ?: ($usuario->name ?: Str::before($email, '@'));
        $usuario->rol = $rol;
        $usuario->activo = ! $this->option('desactivar');

        if ($nuevo || $this->option('contrasena')) {
            $contrasena = $this->secret('Contraseña (mínimo 12 caracteres)');
            if (mb_strlen((string) $contrasena) < 12) {
                $this->error('La contraseña debe tener al menos 12 caracteres.');

                return self::FAILURE;
            }
            $usuario->password = $contrasena;
        }

        if ($this->option('quitar-mfa')) {
            $usuario->app_authentication_secret = null;
            $usuario->app_authentication_recovery_codes = null;
        }

        $usuario->save();

        $this->info(($nuevo ? 'Cuenta creada: ' : 'Cuenta actualizada: ')."{$usuario->email} ({$usuario->rol}, ".($usuario->activo ? 'activa' : 'inactiva').')');

        return self::SUCCESS;
    }
}
