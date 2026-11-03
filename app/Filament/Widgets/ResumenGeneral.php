<?php

namespace App\Filament\Widgets;

use App\Models\Aplicacion;
use App\Models\EstadoApp;
use App\Models\EstadoSistema;
use App\Servicios\Monitoreo\Semaforo;
use App\Support\Formato;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Indicadores generales del Tablero con tendencia de 24 h.
 */
class ResumenGeneral extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';

    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $apps = Aplicacion::with('ultimoEstado')->get();
        $sistema = EstadoSistema::latest('id')->first();
        $tendencia = $this->tendencia24h();

        $conteo = array_fill_keys([Semaforo::VERDE, Semaforo::AMARILLO, Semaforo::ROJO, Semaforo::GRIS], 0);
        $alertas = 0;
        $criticas = 0;
        foreach ($apps as $app) {
            $conteo[$app->ultimoEstado?->semaforo ?? Semaforo::GRIS]++;
            foreach ($app->ultimoEstado?->alertas ?? [] as $a) {
                $alertas += $a['nivel'] !== Semaforo::GRIS ? 1 : 0;
                $criticas += $a['nivel'] === Semaforo::ROJO ? 1 : 0;
            }
        }
        foreach ($sistema?->alertas ?? [] as $a) {
            $alertas++;
            $criticas += $a['nivel'] === Semaforo::ROJO ? 1 : 0;
        }

        $vigiladas = $apps->count() - $conteo[Semaforo::GRIS];
        $peor = Semaforo::peor(Semaforo::VERDE, ...array_keys(array_filter($conteo)));
        $usuarios = $apps->sum(fn ($a) => $a->ultimoEstado?->usuarios_conectados ?? 0);

        return [
            // "En línea" = servicio activo y respondiendo (verde o amarillo). Amarillo es un aviso, no una caída.
            Stat::make('Aplicaciones en línea', ($conteo[Semaforo::VERDE] + $conteo[Semaforo::AMARILLO]).' de '.$vigiladas)
                ->description(match (true) {
                    $conteo[Semaforo::ROJO] > 0 => $conteo[Semaforo::ROJO].' con falla'.($conteo[Semaforo::AMARILLO] ? ' · '.$conteo[Semaforo::AMARILLO].' con avisos' : ''),
                    $conteo[Semaforo::AMARILLO] > 0 => $conteo[Semaforo::VERDE].' sin avisos · '.$conteo[Semaforo::AMARILLO].' con avisos',
                    default => 'Todas sin avisos',
                })
                ->descriptionIcon($peor === Semaforo::VERDE ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedExclamationTriangle)
                ->color(Semaforo::colorFilament($peor))
                ->chart($tendencia['operando'])
                ->icon(Heroicon::OutlinedServerStack),

            Stat::make('Usuarios conectados', number_format($usuarios))
                ->description('Pico de hoy: '.number_format(max([0, ...$tendencia['usuarios']])))
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('primary')
                ->chart($tendencia['usuarios'])
                ->icon(Heroicon::OutlinedUserGroup),

            Stat::make('Alertas activas', (string) $alertas)
                ->description($criticas > 0 ? $criticas.($criticas === 1 ? ' crítica' : ' críticas') : ($alertas > 0 ? 'Ninguna crítica' : 'Sin alertas'))
                ->descriptionIcon($criticas > 0 ? Heroicon::OutlinedFire : Heroicon::OutlinedBellAlert)
                ->color($criticas > 0 ? 'danger' : ($alertas > 0 ? 'warning' : 'success'))
                ->chart($tendencia['alertas'])
                ->icon(Heroicon::OutlinedBell),

            Stat::make('Servidor', $sistema ? 'RAM '.$sistema->porcentajeRam().' %' : 'Sin datos')
                ->description($sistema
                    ? 'Disco '.$sistema->porcentajeDisco().' % · carga '.number_format((float) $sistema->carga_1, 2).' · '.Formato::hace($sistema->created_at)
                    : 'El recolector no ha corrido')
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color(Semaforo::colorFilament($sistema?->semaforo))
                ->chart($tendencia['ram'])
                ->icon(Heroicon::OutlinedServer),
        ];
    }

    /**
     * Series por hora de las últimas 24 h (en caché 1 minuto).
     *
     * @return array{operando: list<int>, usuarios: list<int>, alertas: list<int>, ram: list<int>}
     */
    private function tendencia24h(): array
    {
        return Cache::remember('consola.tendencia24h', 60, function (): array {
            $desde = now()->subDay()->startOfHour();
            $horas = range(0, 24);
            $base = array_fill_keys($horas, []);

            $porHora = $base;
            EstadoApp::where('created_at', '>=', $desde)
                ->orderBy('id')
                ->get(['aplicacion_id', 'created_at', 'usuarios_conectados', 'semaforo', 'alertas'])
                ->each(function ($e) use (&$porHora, $desde) {
                    $h = min(24, (int) floor(($e->created_at->getTimestamp() - $desde->getTimestamp()) / 3600));
                    $porHora[$h][$e->aplicacion_id] = $e; // última lectura de cada app en esa hora
                });

            $ram = $base;
            EstadoSistema::where('created_at', '>=', $desde)
                ->get(['created_at', 'ram_total', 'ram_usada'])
                ->each(function ($s) use (&$ram, $desde) {
                    $h = min(24, (int) floor(($s->created_at->getTimestamp() - $desde->getTimestamp()) / 3600));
                    $ram[$h][] = $s->porcentajeRam() ?? 0;
                });

            $serie = fn (callable $f) => array_values(array_map($f, $porHora));

            return [
                'operando' => $serie(fn ($l) => collect($l)->whereIn('semaforo', [Semaforo::VERDE, Semaforo::AMARILLO])->count()),
                'usuarios' => $serie(fn ($l) => (int) collect($l)->sum('usuarios_conectados')),
                'alertas' => $serie(fn ($l) => (int) collect($l)->sum(fn ($e) => count($e->alertas ?? []))),
                'ram' => array_values(array_map(fn ($v) => $v ? (int) round(array_sum($v) / count($v)) : 0, $ram)),
            ];
        });
    }
}
