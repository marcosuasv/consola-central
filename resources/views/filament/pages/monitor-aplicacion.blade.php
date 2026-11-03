@php
    use App\Servicios\Monitoreo\Semaforo;
    use App\Servicios\Monitoreo\EvaluadorApp;
    use App\Support\Formato;
    use App\Filament\Resources\Aplicaciones\Pages\MonitorAplicacion;

    $vivo = $pestana === 'logs' && $enVivo;
@endphp

<x-filament-panels::page>
    {{-- Refresco: 5 s con logs en vivo, 15 s en lo demás --}}
    <div @if ($vivo) wire:poll.5s @else wire:poll.15s @endif wire:key="poll-{{ $vivo ? 'vivo' : 'normal' }}" class="cc-monitor">

        {{-- Cabecera de estado --}}
        <div class="cc-estado-cabeza cc-borde-{{ $color }}">
            <div class="cc-estado-semaforo">
                <span class="cc-foco cc-{{ $color }}"></span>
                <div>
                    <div class="cc-estado-titulo">{{ Semaforo::etiqueta($estado ? $color : null) }}</div>
                    <div class="cc-estado-sub">
                        {{ $estado ? 'Última lectura '.Formato::hace($estado->created_at) : 'Sin lecturas del recolector todavía' }}
                    </div>
                </div>
            </div>
            <div class="cc-unidades">
                @if ($app->esOctane())
                    @foreach (['Servicio' => $estado?->servicio_estado, 'Cola' => $estado?->cola_estado, 'Scheduler' => $estado?->timer_estado] as $nombre => $e)
                        <span class="cc-pill cc-{{ $e === 'active' ? 'verde' : ($e === null ? 'gris' : ($app->es_pruebas ? 'gris' : 'rojo')) }}">
                            <span class="cc-punto"></span>{{ $nombre }}: {{ $e ? EvaluadorApp::estadoLegible($e) : '—' }}
                        </span>
                    @endforeach
                @else
                    <span class="cc-pill cc-{{ $estado?->servicio_estado === 'active' ? 'verde' : 'rojo' }}">
                        <span class="cc-punto"></span>PHP-FPM: {{ EvaluadorApp::estadoLegible($estado?->servicio_estado) }}
                    </span>
                @endif
                @if ($app->es_pruebas) <span class="cc-chip">Pruebas</span> @endif
                @if ($app->publica_internet) <span class="cc-chip cc-chip-aviso">Publicada a internet</span> @endif
            </div>
        </div>

        {{-- Indicadores --}}
        <div class="cc-kpis">
            @php
                $kpis = [
                    ['Usuarios conectados', Formato::numero($estado?->usuarios_conectados), $estado?->sesiones_invitado ? $estado->sesiones_invitado.' sesiones sin login' : null, null],
                    ['Respuesta', $estado?->http_codigo ? Formato::milisegundos($estado->latencia_ms) : 'Sin respuesta', $estado?->http_codigo ? 'HTTP '.$estado->http_codigo : null, ($estado?->latencia_ms ?? 0) > config('consola.umbrales.latencia_ms') || ($estado && ! $estado->http_codigo) ? 'amarillo' : null],
                    ['Activa hace', $estado?->servicioActivo() ? Formato::duracion($estado->activo_desde) : 'Detenida', $estado?->activo_desde?->format('d/m/Y H:i'), null],
                    ['Memoria', Formato::bytes($estado?->memoria_bytes), $estado?->pid ? 'PID '.$estado->pid : null, null],
                    ['Reinicios 24 h', Formato::numero($estado?->reinicios_24h), null, $estado?->reinicios_24h ? 'amarillo' : null],
                    ['Errores en log 24 h', Formato::numero($estado?->errores_log_24h), null, $estado?->errores_log_24h ? 'amarillo' : null],
                    ['Jobs pendientes', Formato::numero($estado?->jobs_pendientes), null, ($estado?->jobs_pendientes ?? 0) > config('consola.umbrales.pendientes') ? 'amarillo' : null],
                    ['Jobs fallidos 24 h', Formato::numero($estado?->jobs_fallidos_24h), null, ($estado?->jobs_fallidos_24h ?? 0) > config('consola.umbrales.fallidos_24h') ? 'rojo' : (($estado?->jobs_fallidos_24h ?? 0) > 0 ? 'amarillo' : null)],
                ];
            @endphp
            @foreach ($kpis as [$etiqueta, $valor, $sub, $tono])
                <div class="cc-kpi">
                    <span class="cc-metrica-etiqueta">{{ $etiqueta }}</span>
                    <span class="cc-kpi-valor {{ $tono ? 'cc-texto-'.$tono : '' }}">{{ $valor }}</span>
                    @if ($sub) <span class="cc-sis-sub">{{ $sub }}</span> @endif
                </div>
            @endforeach
        </div>

        {{-- Pestañas --}}
        <x-filament::tabs class="cc-pestanas">
            @foreach (MonitorAplicacion::PESTANAS as $clave => $nombre)
                <x-filament::tabs.item :active="$pestana === $clave" wire:click="cambiarPestana('{{ $clave }}')">
                    {{ $nombre }}
                    @if ($clave === 'resumen' && count($estado?->alertas ?? []))
                        <x-slot name="badge">{{ count($estado->alertas) }}</x-slot>
                    @endif
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="cc-panel">
            @include('filament.partes.monitor-'.$pestana)
        </div>
    </div>
</x-filament-panels::page>
