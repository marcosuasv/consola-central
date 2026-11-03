<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar el último acceso de cada usuario
        Event::listen(Login::class, function (Login $evento): void {
            if (method_exists($evento->user, 'forceFill')) {
                $evento->user->forceFill(['ultimo_acceso' => now()])->saveQuietly();
            }
        });
    }
}
