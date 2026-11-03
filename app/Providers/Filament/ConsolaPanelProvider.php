<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tablero;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ConsolaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('consola')
            ->path('')
            ->login()
            ->profile(isSimple: false)
            // Verificación en dos pasos con app (Google Authenticator, Microsoft Authenticator...).
            // Para administradores es obligatoria para usar los controles (ver User::puedeControlar).
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            // Identidad: azul institucional sobre grises pizarra; el semáforo usa verde/ámbar/rojo.
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->brandName('Consola Central')
            ->brandLogo(fn () => view('filament.marca'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.svg'))
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->spa()
            ->unsavedChangesAlerts()
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Tablero::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Monitoreo'),
                NavigationGroup::make('Seguridad'),
                NavigationGroup::make('Configuración'),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.estilos')->render())
            ->renderHook(PanelsRenderHook::CONTENT_START, fn (): string => view('filament.partes.aviso-mfa')->render())
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => view('filament.partes.rol-usuario')->render())
            ->renderHook(PanelsRenderHook::FOOTER, fn (): string => view('filament.partes.pie')->render())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
