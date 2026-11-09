@php
    use App\Support\Formato;
    use App\Servicios\Monitoreo\EvaluadorApp;
@endphp

{{-- Barra de la cola: estado del worker y acciones generales --}}
<div class="cc-barra-cola">
    <div class="cc-barra-cola-datos">
        @if ($app->esOctane() && $app->servicio_cola)
            <span class="cc-pill cc-{{ $estado?->cola_estado === 'active' ? 'verde' : ($app->es_pruebas ? 'gris' : 'rojo') }}">
                <span class="cc-punto"></span>Worker {{ EvaluadorApp::estadoLegible($estado?->cola_estado) }}
            </span>
            <code class="cc-nota">{{ $app->servicio_cola }}</code>
        @endif
        <span class="cc-sis-sub">
            {{ $pendientes === null ? 'Pendientes: sin datos' : count($pendientes).(count($pendientes) >= 50 ? '+' : '').' pendientes' }}
            · {{ $total_fallidos === null ? 'fallidos: sin datos' : $total_fallidos.' fallidos en total' }}
        </span>
    </div>
    <div class="cc-barra-cola-acciones">
        @if ($total_fallidos)
            {{ $this->reintentarTodosAction }}
        @endif
        {{ $this->reiniciarWorkersAction }}
    </div>
</div>

<div class="cc-dos-columnas">
    <section class="cc-caja">
        <div class="cc-grafica-cabeza">
            <h3 class="cc-caja-titulo">Fallidos</h3>
            @if ($fallidos && $total_fallidos > count($fallidos))
                <span class="cc-sis-sub">mostrando los {{ count($fallidos) }} más recientes de {{ $total_fallidos }}</span>
            @endif
        </div>
        @if ($fallidos === null)
            <div class="cc-sin-datos">Sin datos (la app no tiene tabla <code>failed_jobs</code> o no hay permiso de lectura).</div>
        @elseif ($fallidos === [])
            <div class="cc-vacio">
                <x-filament::icon icon="heroicon-o-check-circle" class="cc-ico-grande cc-texto-verde" />
                <p>No hay jobs fallidos.</p>
            </div>
        @else
            <div class="cc-lista-fallidos">
                @foreach ($fallidos as $j)
                    <details class="cc-fallido" wire:key="fallido-{{ $j['id'] }}">
                        <summary>
                            <div class="cc-fallido-info">
                                <span class="cc-fallido-job">{{ $j['job'] }}</span>
                                <span class="cc-sis-sub" title="{{ $j['fecha']->format('d/m/Y H:i:s') }}">
                                    {{ Formato::hace($j['fecha']) }} · cola {{ $j['cola'] ?? '—' }} · #{{ $j['id'] }}
                                </span>
                                <span class="cc-error-corto">{{ $j['error'] }}</span>
                            </div>
                            <div class="cc-fallido-acciones" onclick="event.stopPropagation()">
                                {{ ($this->reintentarJobAction)(['job' => $j['uuid'] ?? (string) $j['id'], 'nombre' => $j['job']]) }}
                            </div>
                        </summary>
                        <pre class="cc-log cc-log-crudo cc-fallido-detalle">{{ $j['detalle'] }}</pre>
                    </details>
                @endforeach
            </div>
        @endif
    </section>

    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Pendientes</h3>
        @if ($pendientes === null)
            <div class="cc-sin-datos">Sin datos (la app no tiene tabla <code>jobs</code> o no hay permiso de lectura).</div>
        @elseif ($pendientes === [])
            <div class="cc-vacio">
                <x-filament::icon icon="heroicon-o-inbox" class="cc-ico-grande" />
                <p>La cola está vacía.</p>
            </div>
        @else
            <div class="cc-tabla-contenedor">
                <table class="cc-tabla">
                    <thead><tr><th>#</th><th>Job</th><th>Cola</th><th>Intentos</th><th>Creado</th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $j)
                            <tr>
                                <td>{{ $j['id'] }}</td>
                                <td>{{ $j['job'] }} @if ($j['reservado']) <span class="cc-chip">en proceso</span> @endif</td>
                                <td>{{ $j['cola'] }}</td>
                                <td>{{ $j['intentos'] }}</td>
                                <td title="{{ $j['creado']->format('d/m/Y H:i:s') }}">{{ Formato::hace($j['creado']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

@unless (auth()->user()?->esAdmin())
    <p class="cc-nota">Reintentar jobs y reiniciar workers requiere rol de administrador.</p>
@endunless
