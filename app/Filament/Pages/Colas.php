<?php

namespace App\Filament\Pages;

use App\Filament\Acciones\AccionesControl;
use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Models\Aplicacion;
use App\Servicios\Control\ControlAplicacion;
use App\Servicios\Monitoreo\EvaluadorApp;
use App\Servicios\Monitoreo\LecturaApps;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Vista general de las colas de todas las apps (Paso 10).
 * El detalle de cada job está en la pestaña Colas del monitor de la app.
 */
class Colas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoreo';

    protected static ?string $title = 'Colas';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.colas';

    /** @var array<int, ?int> total de fallidos por app, calculado una vez por render */
    private array $totales = [];

    public function getSubheading(): ?string
    {
        return 'Jobs pendientes y fallidos de cada aplicación. Los reintentos pasan por consola-ctl y quedan en la auditoría.';
    }

    public static function getNavigationBadge(): ?string
    {
        $fallidos = Aplicacion::query()->with('ultimoEstado')->get()->sum(fn ($a) => $a->ultimoEstado?->jobs_fallidos_24h ?? 0);

        return $fallidos > 0 ? (string) $fallidos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jobs fallidos en las últimas 24 h';
    }

    private function totalFallidos(Aplicacion $app): ?int
    {
        return $this->totales[$app->id] ??= app(LecturaApps::class)->totalFallidos($app);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Aplicacion::query()->with('ultimoEstado')->where('monitorear', true))
            ->defaultSort('orden')
            ->poll('30s')
            ->paginated(false)
            ->recordUrl(fn (Aplicacion $record) => AplicacionResource::getUrl('monitor', ['record' => $record, 'pestana' => 'colas']))
            ->columns([
                TextColumn::make('nombre')
                    ->label('Aplicación')
                    ->weight('semibold')
                    ->description(fn (Aplicacion $r) => $r->esFpm() ? 'PHP-FPM · '.$r->carpeta : ($r->servicio_cola ?? 'sin worker')),
                TextColumn::make('worker')
                    ->label('Worker')
                    ->badge()
                    ->state(fn (Aplicacion $r) => $r->esOctane() && $r->servicio_cola
                        ? EvaluadorApp::estadoLegible($r->ultimoEstado?->cola_estado)
                        : 'n/a')
                    ->color(fn (Aplicacion $r) => match (true) {
                        ! $r->esOctane() || ! $r->servicio_cola => 'gray',
                        $r->ultimoEstado?->cola_estado === 'active' => 'success',
                        $r->es_pruebas => 'gray',
                        default => 'danger',
                    })
                    ->icon(fn (Aplicacion $r) => $r->ultimoEstado?->cola_estado === 'active' ? Heroicon::OutlinedPlayCircle : Heroicon::OutlinedPauseCircle),
                TextColumn::make('pendientes')
                    ->label('Pendientes')
                    ->numeric()
                    ->state(fn (Aplicacion $r) => $r->ultimoEstado?->jobs_pendientes)
                    ->placeholder('sin datos')
                    ->color(fn ($state) => $state > config('consola.umbrales.pendientes') ? 'warning' : null),
                TextColumn::make('fallidos24h')
                    ->label('Fallidos 24 h')
                    ->numeric()
                    ->state(fn (Aplicacion $r) => $r->ultimoEstado?->jobs_fallidos_24h)
                    ->placeholder('sin datos')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state > config('consola.umbrales.fallidos_24h') => 'danger',
                        $state > 0 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('fallidosTotal')
                    ->label('Fallidos (total)')
                    ->numeric()
                    ->state(fn (Aplicacion $r) => $this->totalFallidos($r))
                    ->placeholder('sin datos'),
                TextColumn::make('actualizado')
                    ->label('Lectura')
                    ->state(fn (Aplicacion $r) => $r->ultimoEstado?->created_at)
                    ->since()
                    ->color('gray'),
            ])
            ->recordActions([
                AccionesControl::reintentarTodos(fn (array $arguments, ?Aplicacion $record = null) => $record)
                    ->iconButton()
                    ->hidden(fn (Aplicacion $record) => ! $this->totalFallidos($record)),
                AccionesControl::fija(ControlAplicacion::REINICIAR_COLA, fn () => null, 'reiniciarWorkers')
                    ->label('Reiniciar workers'),
                Action::make('ver')
                    ->label('Ver jobs')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Aplicacion $record) => AplicacionResource::getUrl('monitor', ['record' => $record, 'pestana' => 'colas'])),
            ])
            ->emptyStateHeading('Sin aplicaciones monitoreadas');
    }
}
