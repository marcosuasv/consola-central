<?php

namespace App\Filament\Resources\Aplicaciones\Pages;

use App\Filament\Acciones\AccionesControl;
use App\Filament\Pages\Tablero;
use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Filament\Resources\Auditorias\AuditoriaResource;
use App\Models\Aplicacion;
use App\Models\EstadoApp;
use App\Servicios\Control\ControlAplicacion;
use App\Servicios\Monitoreo\AnalizadorMantenimiento;
use App\Servicios\Monitoreo\LectorLog;
use App\Servicios\Monitoreo\LecturaApps;
use App\Servicios\Monitoreo\Semaforo;
use App\Servicios\Monitoreo\Systemd;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/**
 * Detalle en vivo de una app: resumen, usuarios conectados, logs, colas y mantenimiento.
 * Todo es de solo lectura; los botones de control llegan en el Paso 7.
 *
 * @property Aplicacion $record
 */
class MonitorAplicacion extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AplicacionResource::class;

    protected string $view = 'filament.pages.monitor-aplicacion';

    public const PESTANAS = [
        'resumen' => 'Resumen',
        'usuarios' => 'Usuarios conectados',
        'logs' => 'Logs',
        'colas' => 'Colas',
        'mantenimiento' => 'Mantenimiento',
    ];

    public const FUENTES_LOG = [
        'laravel' => 'Laravel',
        'servicio' => 'systemd (app)',
        'cola' => 'systemd (cola)',
        'nginx' => 'Nginx acceso',
        'nginx_error' => 'Nginx errores',
    ];

    #[Url]
    public string $pestana = 'resumen';

    public string $fuente = 'laravel';

    public string $nivel = '';

    public string $buscar = '';

    public int $lineas = 200;

    public bool $enVivo = true;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'resumen';
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->nombre;
    }

    public function getSubheading(): ?string
    {
        return $this->record->ruta().' · '.($this->record->esFpm() ? 'PHP-FPM, pool '.$this->record->pool_fpm : 'Octane, servicio '.$this->record->servicio);
    }

    public function getBreadcrumbs(): array
    {
        return [
            Tablero::getUrl() => 'Tablero',
            $this->record->nombre,
        ];
    }

    protected function getHeaderActions(): array
    {
        $app = fn (): Aplicacion => $this->record;
        $activa = fn (): bool => $this->record->ultimoEstado()->value('servicio_estado') === 'active';

        return [
            AccionesControl::fija(ControlAplicacion::ENCENDER, $app)->hidden($activa),
            AccionesControl::fija(ControlAplicacion::APAGAR, $app)->hidden(fn () => ! $activa()),
            AccionesControl::fija(ControlAplicacion::REINICIAR, $app),
            ActionGroup::make([
                ActionGroup::make([
                    AccionesControl::fija(ControlAplicacion::RECARGAR, $app),
                    AccionesControl::fija(ControlAplicacion::REINICIAR_COLA, $app),
                ])->dropdown(false),
                ActionGroup::make([
                    Action::make('abrir')
                        ->label('Abrir app')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url($this->record->url, shouldOpenInNewTab: true)
                        ->visible(filled($this->record->url)),
                    Action::make('editar')
                        ->label('Editar ficha')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->url(AplicacionResource::getUrl('edit', ['record' => $this->record])),
                    Action::make('auditoria')
                        ->label('Ver auditoría')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->url(fn () => AuditoriaResource::getUrl('index', ['filters' => ['aplicacion_id' => ['value' => $this->record->id]]])),
                ])->dropdown(false),
            ])
                ->label('Más')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->button()
                ->color('gray'),
        ];
    }

    // ---- Acciones de la pestaña Colas (Paso 10) ----

    public function reintentarJobAction(): Action
    {
        return AccionesControl::reintentarJob(fn () => $this->record);
    }

    public function reintentarTodosAction(): Action
    {
        return AccionesControl::reintentarTodos(fn () => $this->record);
    }

    public function reiniciarWorkersAction(): Action
    {
        return AccionesControl::fija(ControlAplicacion::REINICIAR_COLA, fn () => $this->record, 'reiniciarWorkers')
            ->label('Reiniciar workers');
    }

    public function cambiarPestana(string $pestana): void
    {
        if (array_key_exists($pestana, self::PESTANAS)) {
            $this->pestana = $pestana;
        }
    }

    public function updated(string $propiedad): void
    {
        if ($propiedad === 'fuente' && ! array_key_exists($this->fuente, self::FUENTES_LOG)) {
            $this->fuente = 'laravel';
        }
        if ($propiedad === 'nivel' && ! in_array($this->nivel, ['', 'ERROR', 'WARNING'], true)) {
            $this->nivel = '';
        }
        if ($propiedad === 'lineas') {
            $this->lineas = max(50, min($this->lineas, (int) config('consola.limites.lineas_log', 500)));
        }
        $this->buscar = mb_substr($this->buscar, 0, 100);
    }

    protected function getViewData(): array
    {
        $app = $this->record;
        $estado = $app->ultimoEstado()->first();

        $datos = [
            'app' => $app,
            'estado' => $estado,
            'color' => $estado?->semaforo ?? Semaforo::GRIS,
        ];

        // Solo se consulta lo que necesita la pestaña visible
        return $datos + match ($this->pestana) {
            'resumen' => $this->datosResumen($app),
            'usuarios' => ['usuarios' => app(LecturaApps::class)->usuariosConectados($app)],
            'logs' => $this->datosLogs($app),
            'colas' => [
                'pendientes' => app(LecturaApps::class)->listaPendientes($app),
                'fallidos' => app(LecturaApps::class)->listaFallidos($app),
                'total_fallidos' => app(LecturaApps::class)->totalFallidos($app),
            ],
            'mantenimiento' => $this->datosMantenimiento($app, $estado),
            default => [],
        };
    }

    private function datosResumen(Aplicacion $app): array
    {
        // Últimas 24 h agrupadas en bloques de 15 minutos para las gráficas
        $lecturas = $app->estados()
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at')
            ->get(['created_at', 'latencia_ms', 'memoria_bytes', 'usuarios_conectados', 'semaforo']);

        $bloques = array_fill(0, 96, ['latencia' => [], 'memoria' => [], 'usuarios' => [], 'semaforo' => null]);
        $inicio = now()->subDay()->getTimestamp();

        foreach ($lecturas as $l) {
            $i = min(95, intdiv($l->created_at->getTimestamp() - $inicio, 900));
            if ($i < 0) {
                continue;
            }
            $bloques[$i]['latencia'][] = $l->latencia_ms;
            $bloques[$i]['memoria'][] = $l->memoria_bytes;
            $bloques[$i]['usuarios'][] = $l->usuarios_conectados;
            $bloques[$i]['semaforo'] = Semaforo::peor($bloques[$i]['semaforo'] ?? Semaforo::GRIS, $l->semaforo);
        }

        $serie = function (string $campo, string $modo = 'promedio') use ($bloques): array {
            return array_map(function ($b) use ($campo, $modo) {
                $v = array_filter($b[$campo], fn ($x) => $x !== null);

                return $v === [] ? null : ($modo === 'max' ? max($v) : array_sum($v) / count($v));
            }, $bloques);
        };

        $latencias = $serie('latencia');
        $memorias = $serie('memoria');
        $usuarios = $serie('usuarios', 'max');

        $maximo = fn (array $s) => ($v = array_filter($s, fn ($x) => $x !== null)) ? max($v) : null;

        return [
            'graficas' => [
                ['titulo' => 'Tiempo de respuesta', 'trazo' => Formato::sparkline($latencias), 'pico' => Formato::milisegundos($maximo($latencias) ? (int) $maximo($latencias) : null)],
                ['titulo' => 'Memoria', 'trazo' => Formato::sparkline($memorias), 'pico' => Formato::bytes($maximo($memorias) ? (int) $maximo($memorias) : null)],
                ['titulo' => 'Usuarios conectados', 'trazo' => Formato::sparkline($usuarios), 'pico' => Formato::numero($maximo($usuarios) !== null ? (int) $maximo($usuarios) : null)],
            ],
            'linea_tiempo' => array_column($bloques, 'semaforo'),
            'disponibilidad' => $this->disponibilidad($lecturas),
        ];
    }

    /** % de lecturas de las últimas 24 h sin rojo. */
    private function disponibilidad($lecturas): ?float
    {
        $validas = $lecturas->where('semaforo', '!=', Semaforo::GRIS);
        if ($validas->isEmpty()) {
            return null;
        }

        return round($validas->where('semaforo', '!=', Semaforo::ROJO)->count() * 100 / $validas->count(), 1);
    }

    private function datosLogs(Aplicacion $app): array
    {
        $lector = app(LectorLog::class);
        $systemd = app(Systemd::class);
        $lineas = max(50, min($this->lineas, (int) config('consola.limites.lineas_log', 500)));
        $ruta = null;
        $entradas = null;
        $crudas = null;

        try {
            switch ($this->fuente) {
                case 'laravel':
                    $ruta = $lector->logLaravel($app);
                    $leidas = $lector->cola($ruta, $lineas * 3);
                    $entradas = $leidas === null ? null : array_reverse(array_slice($lector->entradasLaravel($leidas, $this->nivel ?: null, $this->buscar ?: null), -$lineas));
                    break;
                case 'servicio':
                case 'cola':
                    $unidad = $this->fuente === 'cola' ? $app->servicio_cola : ($app->esFpm() ? config('consola.servicio_fpm') : $app->servicio);
                    $ruta = $unidad ? 'journalctl -u '.$unidad : null;
                    $crudas = $unidad ? $systemd->journal($unidad, $lineas) : null;
                    break;
                case 'nginx':
                case 'nginx_error':
                    $ruta = $this->fuente === 'nginx' ? $app->log_nginx : ($app->log_nginx ? str_replace('.access.log', '.error.log', $app->log_nginx) : null);
                    $crudas = $lector->cola($ruta, $lineas);
                    break;
            }
        } catch (\Throwable $e) {
            report($e);
            $crudas = null;
        }

        if ($crudas !== null) {
            $crudas = array_map([LectorLog::class, 'ocultarSecretos'], $crudas);
            if ($this->buscar !== '') {
                $crudas = array_values(array_filter($crudas, fn ($l) => mb_stripos($l, $this->buscar) !== false));
            }
            $crudas = array_reverse($crudas);
        }

        return ['ruta_log' => $ruta, 'entradas' => $entradas, 'crudas' => $crudas];
    }

    private function datosMantenimiento(Aplicacion $app, ?EstadoApp $estado): array
    {
        $analizador = app(AnalizadorMantenimiento::class);
        $version = $analizador->versionLaravel($app);
        $soporte = $analizador->soporteLaravel($version);
        $git = $analizador->cambiosGit($app);

        return [
            'version' => $version,
            'soporte' => $soporte,
            'git' => $git,
            'recomendaciones' => $analizador->recomendaciones($app, $estado, $soporte, $git),
        ];
    }
}
