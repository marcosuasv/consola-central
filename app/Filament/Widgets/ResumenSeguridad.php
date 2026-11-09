<?php

namespace App\Filament\Widgets;

use App\Models\Aplicacion;
use App\Servicios\Seguridad\AnalizadorNginx;
use App\Servicios\Seguridad\Fail2ban;
use App\Servicios\Seguridad\RegistroConsolaCtl;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Indicadores de la página Seguridad.
 */
class ResumenSeguridad extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $f2b = app(Fail2ban::class);
        $estado = $f2b->estado();
        $actuales = collect($estado['reglas'])->sum('actuales');
        $historial = Fail2ban::resumir($f2b->historial(7));

        $publica = Aplicacion::where('publica_internet', true)->first();
        $visitas = $publica ? app(AnalizadorNginx::class)->analizar($publica) : null;

        $rechazos = app(RegistroConsolaCtl::class)->rechazosDesde(now()->toImmutable()->subDay());

        return [
            Stat::make('IPs bloqueadas ahora', $estado['error'] ? '—' : (string) $actuales)
                ->description($estado['error'] ? 'No se pudo consultar Fail2ban' : count($estado['reglas']).' reglas activas')
                ->descriptionIcon($estado['error'] ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedShieldCheck)
                ->color($estado['error'] ? 'danger' : ($actuales > 0 ? 'warning' : 'success'))
                ->icon(Heroicon::OutlinedNoSymbol),

            Stat::make('Bloqueos en 7 días', (string) $historial['bloqueos'])
                ->description(number_format($historial['intentos']).' intentos detectados')
                ->descriptionIcon(Heroicon::OutlinedFingerPrint)
                ->chart(array_values($historial['por_dia']))
                ->color('primary')
                ->icon(Heroicon::OutlinedCalendarDays),

            Stat::make($publica ? "Visitas de internet · {$publica->nombre}" : 'Visitas de internet', $visitas && $visitas['disponible'] ? number_format($visitas['publicas']) : '—')
                ->description($visitas && $visitas['disponible']
                    ? $visitas['ips_publicas'].' IPs · '.$visitas['sospechosas'].' rutas sospechosas (24 h)'
                    : 'Sin log de Nginx')
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->chart($visitas['por_hora'] ?? null)
                ->color(($visitas['sospechosas'] ?? 0) > 0 ? 'warning' : 'info')
                ->icon(Heroicon::OutlinedGlobeAmericas),

            Stat::make('Rechazos de consola-ctl', (string) $rechazos)
                ->description($rechazos > 0 ? 'Acciones fuera de lo permitido (24 h)' : 'Sin intentos rechazados (24 h)')
                ->descriptionIcon($rechazos > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($rechazos > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedCommandLine),
        ];
    }
}
