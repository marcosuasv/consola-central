<?php

namespace App\Filament\Pages;

use App\Models\Aplicacion;
use App\Models\EstadoSistema;
use App\Servicios\Monitoreo\Semaforo;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

/**
 * Página principal: una tarjeta por app con semáforo y el estado del servidor.
 * Los datos vienen de la última lectura de consola:recolectar; se refresca cada 15 s.
 */
class Tablero extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $title = 'Tablero';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.tablero';

    public bool $mostrarPruebas = true;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getSubheading(): ?string
    {
        return 'Estado en tiempo real de las aplicaciones del servidor DebWebServ.';
    }

    protected function getViewData(): array
    {
        $apps = Aplicacion::query()
            ->with('ultimoEstado')
            ->when(! $this->mostrarPruebas, fn ($q) => $q->where('es_pruebas', false))
            ->ordenadas()
            ->get();

        $sistema = EstadoSistema::latest('id')->first();

        $conteo = [Semaforo::VERDE => 0, Semaforo::AMARILLO => 0, Semaforo::ROJO => 0, Semaforo::GRIS => 0];
        foreach ($apps as $app) {
            $color = $app->ultimoEstado?->semaforo ?? Semaforo::GRIS;
            $conteo[$color]++;
        }

        $ultima = $apps->pluck('ultimoEstado.created_at')->filter()->max();

        return [
            'apps' => $apps,
            'sistema' => $sistema,
            'conteo' => $conteo,
            'usuarios' => $apps->sum(fn ($a) => $a->ultimoEstado?->usuarios_conectados ?? 0),
            'alertas' => $apps->sum(fn ($a) => count(array_filter($a->ultimoEstado?->alertas ?? [], fn ($x) => $x['nivel'] !== Semaforo::GRIS)))
                + count($sistema?->alertas ?? []),
            'ultima' => $ultima,
            // Si el recolector lleva más de 3 minutos sin correr, se avisa en pantalla
            'desactualizado' => $ultima === null || $ultima->lt(now()->subMinutes(3)),
        ];
    }
}
