@php
    use App\Servicios\Monitoreo\Semaforo;
    use App\Support\Formato;
@endphp

<x-filament-panels::page>
    <div wire:poll.15s class="cc-tablero">

        @if ($desactualizado)
            <div class="cc-aviso cc-aviso-rojo" role="alert">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="cc-ico" />
                <div>
                    <strong>Los datos no están al día.</strong>
                    La última lectura fue {{ Formato::hace($ultima) }}. Revisa el recolector con
                    <code>systemctl status consola-recolector.timer</code>.
                </div>
            </div>
        @endif

        {{-- Servidor --}}
        <x-filament::section icon="heroicon-o-server" :collapsible="true" :compact="true">
            <x-slot name="heading">Servidor DebWebServ</x-slot>
            <x-slot name="description">10.2.0.170 · Debian 12 · servicios base, recursos y certificados</x-slot>
            <x-slot name="afterHeader">
                <span class="cc-pill cc-{{ $sistema?->semaforo ?? 'gris' }}"><span class="cc-punto"></span>{{ Semaforo::etiqueta($sistema?->semaforo) }}</span>
            </x-slot>
            @include('filament.partes.sistema', ['sistema' => $sistema])
        </x-filament::section>

        {{-- Aplicaciones --}}
        <div class="cc-encabezado-seccion">
            <div>
                <h2>Aplicaciones</h2>
                <p class="cc-nota">Clic en una tarjeta para ver el detalle. Última lectura {{ Formato::hace($ultima) }}.</p>
            </div>
            <div class="cc-leyenda">
                @foreach ([Semaforo::VERDE, Semaforo::AMARILLO, Semaforo::ROJO, Semaforo::GRIS] as $c)
                    <span class="cc-pill cc-{{ $c }}" title="{{ Semaforo::etiqueta($c) }}"><span class="cc-punto"></span>{{ Semaforo::etiqueta($c) }} · {{ $conteo[$c] }}</span>
                @endforeach
                <label class="cc-interruptor">
                    <input type="checkbox" wire:model.live="mostrarPruebas">
                    <span>Pruebas</span>
                </label>
            </div>
        </div>

        @if ($apps->isEmpty())
            <x-filament::section>
                <div class="cc-vacio">
                    <x-filament::icon icon="heroicon-o-server-stack" class="cc-ico-grande" />
                    <p>No hay aplicaciones registradas.</p>
                    <x-filament::button tag="a" icon="heroicon-m-plus" :href="\App\Filament\Resources\Aplicaciones\AplicacionResource::getUrl('create')">
                        Registrar aplicación
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <div class="cc-rejilla">
                @foreach ($apps as $app)
                    @include('filament.partes.tarjeta-app', ['app' => $app, 'estado' => $app->ultimoEstado])
                @endforeach
            </div>
        @endif
    </div>

</x-filament-panels::page>
