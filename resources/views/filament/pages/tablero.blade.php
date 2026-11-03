@php
    use App\Servicios\Monitoreo\Semaforo;
    use App\Support\Formato;
@endphp

<x-filament-panels::page>
    <div wire:poll.15s class="cc-tablero">

        {{-- Resumen general --}}
        <div class="cc-resumen">
            <div class="cc-resumen-item">
                <span class="cc-resumen-etiqueta">Aplicaciones</span>
                <div class="cc-resumen-semaforos">
                    @foreach ([Semaforo::VERDE, Semaforo::AMARILLO, Semaforo::ROJO, Semaforo::GRIS] as $color)
                        <span class="cc-pill cc-{{ $color }}" title="{{ Semaforo::etiqueta($color) }}">
                            <span class="cc-punto"></span>{{ $conteo[$color] }}
                        </span>
                    @endforeach
                </div>
            </div>
            <div class="cc-resumen-item">
                <span class="cc-resumen-etiqueta">Usuarios conectados</span>
                <span class="cc-resumen-valor">{{ number_format($usuarios) }}</span>
            </div>
            <div class="cc-resumen-item">
                <span class="cc-resumen-etiqueta">Alertas activas</span>
                <span class="cc-resumen-valor {{ $alertas > 0 ? 'cc-texto-amarillo' : '' }}">{{ $alertas }}</span>
            </div>
            <div class="cc-resumen-item">
                <span class="cc-resumen-etiqueta">Servidor</span>
                <span class="cc-pill cc-{{ $sistema?->semaforo ?? 'gris' }}">
                    <span class="cc-punto"></span>{{ Semaforo::etiqueta($sistema?->semaforo) }}
                </span>
            </div>
            <div class="cc-resumen-item cc-resumen-fin">
                <span class="cc-resumen-etiqueta">Última lectura</span>
                <span class="cc-resumen-sub {{ $desactualizado ? 'cc-texto-rojo' : '' }}">{{ Formato::hace($ultima) }}</span>
            </div>
        </div>

        @if ($desactualizado)
            <div class="cc-aviso cc-aviso-rojo">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="cc-ico" />
                <div>
                    <strong>Los datos no están al día.</strong>
                    El recolector (<code>php artisan consola:recolectar</code>) no ha corrido en los últimos 3 minutos.
                    Revisa el timer <code>consola-recolector.timer</code> con <code>systemctl status consola-recolector.timer</code>.
                </div>
            </div>
        @endif

        {{-- Servidor --}}
        @include('filament.partes.sistema', ['sistema' => $sistema])

        {{-- Aplicaciones --}}
        <div class="cc-encabezado-seccion">
            <h2>Aplicaciones</h2>
            <label class="cc-interruptor">
                <input type="checkbox" wire:model.live="mostrarPruebas">
                <span>Mostrar entornos de pruebas</span>
            </label>
        </div>

        @if ($apps->isEmpty())
            <div class="cc-vacio">
                <x-filament::icon icon="heroicon-o-server-stack" class="cc-ico-grande" />
                <p>No hay aplicaciones registradas.</p>
                <x-filament::button tag="a" :href="\App\Filament\Resources\Aplicaciones\AplicacionResource::getUrl('create')">
                    Registrar aplicación
                </x-filament::button>
            </div>
        @else
            <div class="cc-rejilla">
                @foreach ($apps as $app)
                    @include('filament.partes.tarjeta-app', ['app' => $app, 'estado' => $app->ultimoEstado])
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
