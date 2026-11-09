<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ResumenSeguridad;
use App\Models\Aplicacion;
use App\Servicios\Control\ConsolaCtl;
use App\Servicios\Seguridad\AnalizadorNginx;
use App\Servicios\Seguridad\Fail2ban;
use App\Servicios\Seguridad\RegistroConsolaCtl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Paso 11: Fail2ban (bloqueos actuales y desbloqueo), historial de 7 días,
 * visitas desde IPs públicas por app y registro de consola-ctl.
 */
class Seguridad extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Seguridad';

    protected static ?string $title = 'Seguridad';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.seguridad';

    public const PESTANAS = [
        'bloqueos' => 'Bloqueos actuales',
        'historial' => 'Historial 7 días',
        'visitas' => 'Visitas públicas',
        'consolactl' => 'Registro consola-ctl',
    ];

    #[Url]
    public string $pestana = 'bloqueos';

    #[Url]
    public ?int $app = null;

    public function mount(): void
    {
        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'bloqueos';
        }
    }

    public function getSubheading(): ?string
    {
        return 'Fail2ban, accesos desde internet y acciones rechazadas. Solo lectura salvo «Desbloquear» (administradores).';
    }

    public static function getNavigationBadge(): ?string
    {
        $rechazos = app(RegistroConsolaCtl::class)->rechazosDesde(now()->toImmutable()->subDay());

        return $rechazos > 0 ? (string) $rechazos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Acciones rechazadas por consola-ctl en 24 h';
    }

    protected function getHeaderWidgets(): array
    {
        return [ResumenSeguridad::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualizar')
                ->label('Actualizar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    app(Fail2ban::class)->olvidarEstado();
                    Aplicacion::pluck('id')->each(fn ($id) => cache()->forget("consola.nginx.{$id}.24"));
                    Notification::make()->success()->title('Datos actualizados')->send();
                }),
        ];
    }

    public function cambiarPestana(string $pestana): void
    {
        if (array_key_exists($pestana, self::PESTANAS)) {
            $this->pestana = $pestana;
        }
    }

    public function elegirApp(int $id): void
    {
        $this->app = Aplicacion::whereKey($id)->exists() ? $id : null;
    }

    /** Desbloquear una IP de una regla: consola-ctl f2b unban <regla> <ip>. */
    public function desbloquearAction(): Action
    {
        return Action::make('desbloquear')
            ->label('Desbloquear')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('warning')
            ->size('sm')
            ->visible(fn () => (bool) auth()->user()?->esAdmin())
            ->disabled(fn () => ! auth()->user()?->puedeControlar())
            ->tooltip(fn () => auth()->user()?->puedeControlar() ? null : 'Activa la verificación en dos pasos en tu perfil para usar los controles.')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedLockOpen)
            ->modalHeading(fn (array $arguments) => 'Desbloquear '.($arguments['ip'] ?? ''))
            ->modalDescription(fn (array $arguments) => 'La IP podrá volver a conectarse. Si sigue atacando, Fail2ban la bloqueará de nuevo en la regla «'.($arguments['regla'] ?? '').'».')
            ->modalSubmitActionLabel('Sí, desbloquear')
            ->rateLimit(20)
            ->action(function (array $arguments): void {
                if (! auth()->user()?->puedeControlar()) {
                    Notification::make()->danger()->title('No autorizado')->send();

                    return;
                }

                try {
                    $resultado = app(ConsolaCtl::class)->f2bUnban((string) ($arguments['regla'] ?? ''), (string) ($arguments['ip'] ?? ''));
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title('Datos inválidos')->body($e->getMessage())->send();

                    return;
                }

                app(Fail2ban::class)->olvidarEstado();

                $resultado->ok
                    ? Notification::make()->success()->title("{$arguments['ip']} desbloqueada")->body('Quedó registrado en la auditoría.')->send()
                    : Notification::make()->danger()->persistent()->title('No se pudo desbloquear')->body($resultado->resumen())->send();
            });
    }

    protected function getViewData(): array
    {
        return match ($this->pestana) {
            'bloqueos' => ['f2b' => app(Fail2ban::class)->estado()],
            'historial' => ['resumen' => Fail2ban::resumir(app(Fail2ban::class)->historial(7))],
            'visitas' => $this->datosVisitas(),
            'consolactl' => ['entradas' => app(RegistroConsolaCtl::class)->entradas(200)],
            default => [],
        };
    }

    private function datosVisitas(): array
    {
        $apps = Aplicacion::where('monitorear', true)
            ->orderByDesc('publica_internet')->ordenadas()->get();
        $analizador = app(AnalizadorNginx::class);
        $resumen = $apps->map(fn (Aplicacion $a) => ['app' => $a, 'datos' => $analizador->analizar($a)]);

        $elegida = $resumen->firstWhere('app.id', $this->app) ?? $resumen->first();

        // IPs que hoy están bloqueadas por Fail2ban (para marcarlas en la tabla)
        $bloqueadas = collect(app(Fail2ban::class)->estado()['reglas'])->pluck('ips')->flatten()->flip()->all();

        return ['resumen' => $resumen, 'elegida' => $elegida, 'bloqueadas' => $bloqueadas];
    }
}
