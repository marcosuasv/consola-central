<?php

namespace App\Filament\Widgets;

use App\Models\Respaldo;
use App\Servicios\Respaldos\ServicioRespaldos;
use App\Support\Formato;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenRespaldos extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $servicio = app(ServicioRespaldos::class);
        $objetivos = $servicio->objetivos();
        $limite = now()->subHours((int) config('consola.respaldos.horas_alerta', 26));

        $alDia = collect($objetivos)->filter(fn ($o) => Respaldo::where('base_datos', $o['base'])->where('resultado', 'ok')->where('created_at', '>=', $limite)->exists())->count();
        $ultimo = Respaldo::where('resultado', 'ok')->latest('created_at')->first();
        $fallos = Respaldo::where('resultado', 'error')->where('created_at', '>=', now()->subDays(7))->count();

        $bytes = 0;
        $accesible = false;
        foreach ($objetivos as $o) {
            $archivos = $servicio->archivosEnDisco($o['base']);
            if ($archivos !== null) {
                $accesible = true;
                $bytes += array_sum(array_column($archivos, 'bytes'));
            }
        }

        $porDia = collect(range(6, 0))->map(fn ($d) => Respaldo::where('resultado', 'ok')->whereDate('created_at', now()->subDays($d)->toDateString())->count())->all();

        return [
            Stat::make('Bases al día', $alDia.' de '.count($objetivos))
                ->description('con respaldo correcto en las últimas '.config('consola.respaldos.horas_alerta').' h')
                ->descriptionIcon($alDia === count($objetivos) ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedExclamationTriangle)
                ->color(count($objetivos) === 0 ? 'gray' : ($alDia === count($objetivos) ? 'success' : 'warning'))
                ->icon(Heroicon::OutlinedCircleStack),
            Stat::make('Último respaldo', $ultimo ? Formato::hace($ultimo->created_at) : 'Nunca')
                ->description($ultimo ? $ultimo->base_datos.' · '.Formato::bytes($ultimo->tamano_bytes, 1) : 'Aún no se ha respaldado nada')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('primary')
                ->chart($porDia)
                ->icon(Heroicon::OutlinedArchiveBox),
            Stat::make('Espacio en disco', $accesible ? Formato::bytes($bytes, 1) : '—')
                ->description($accesible ? 'todas las copias conservadas' : 'La consola no puede listar '.config('consola.respaldos.carpeta'))
                ->descriptionIcon(Heroicon::OutlinedServerStack)
                ->color('gray')
                ->icon(Heroicon::OutlinedServer),
            Stat::make('Fallos en 7 días', (string) $fallos)
                ->description($fallos > 0 ? 'Revisa el historial de abajo' : 'Sin fallos')
                ->descriptionIcon($fallos > 0 ? Heroicon::OutlinedXCircle : Heroicon::OutlinedCheckCircle)
                ->color($fallos > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedShieldExclamation),
        ];
    }
}
