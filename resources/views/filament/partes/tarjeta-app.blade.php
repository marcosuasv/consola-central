@php
    use App\Servicios\Monitoreo\Semaforo;
    use App\Support\Formato;
    use App\Filament\Resources\Aplicaciones\AplicacionResource;

    $color = $estado?->semaforo ?? Semaforo::GRIS;
    $alertasVisibles = collect($estado?->alertas ?? [])->sortByDesc(fn ($a) => $a['nivel'] === Semaforo::ROJO)->values();
@endphp

<a href="{{ AplicacionResource::getUrl('monitor', ['record' => $app]) }}" wire:navigate
   class="cc-tarjeta cc-borde-{{ $color }}" wire:key="app-{{ $app->id }}">

    <div class="cc-tarjeta-cabeza">
        <div class="cc-tarjeta-titulo">
            <h3>{{ $app->nombre }}</h3>
            <p>
                {{ $app->esFpm() ? 'PHP-FPM · pool '.$app->pool_fpm : 'Octane · :'.$app->puerto_interno }}
                @if ($app->es_pruebas) <span class="cc-chip">Pruebas</span> @endif
                @if ($app->publica_internet) <span class="cc-chip cc-chip-aviso">Internet</span> @endif
            </p>
        </div>
        <span class="cc-pill cc-{{ $color }}"><span class="cc-punto"></span>{{ Semaforo::etiqueta($estado ? $color : null) }}</span>
    </div>

    @if ($estado)
        <div class="cc-metricas">
            <div class="cc-metrica">
                <span class="cc-metrica-etiqueta">Usuarios</span>
                <span class="cc-metrica-valor">{{ Formato::numero($estado->usuarios_conectados) }}</span>
            </div>
            <div class="cc-metrica">
                <span class="cc-metrica-etiqueta">Respuesta</span>
                <span class="cc-metrica-valor {{ ($estado->latencia_ms ?? 0) > config('consola.umbrales.latencia_ms') ? 'cc-texto-amarillo' : '' }}">
                    {{ $estado->http_codigo ? Formato::milisegundos($estado->latencia_ms) : 'Caída' }}
                </span>
            </div>
            <div class="cc-metrica">
                <span class="cc-metrica-etiqueta">Activa hace</span>
                <span class="cc-metrica-valor">{{ $estado->servicioActivo() ? Formato::duracion($estado->activo_desde) : 'Detenida' }}</span>
            </div>
            <div class="cc-metrica">
                <span class="cc-metrica-etiqueta">Memoria</span>
                <span class="cc-metrica-valor">{{ Formato::bytes($estado->memoria_bytes) }}</span>
            </div>
            <div class="cc-metrica">
                <span class="cc-metrica-etiqueta">Cola</span>
                <span class="cc-metrica-valor">
                    {{ Formato::numero($estado->jobs_pendientes) }}
                    @if ($estado->jobs_fallidos_24h)
                        <small class="cc-texto-rojo">· {{ $estado->jobs_fallidos_24h }} fallidos</small>
                    @endif
                </span>
            </div>
            <div class="cc-metrica">
                <span class="cc-metrica-etiqueta" title="Reinicios en las últimas 24 horas">Reinicios</span>
                <span class="cc-metrica-valor {{ $estado->reinicios_24h ? 'cc-texto-amarillo' : '' }}">{{ Formato::numero($estado->reinicios_24h) }}</span>
            </div>
        </div>

        <div class="cc-alertas">
            @forelse ($alertasVisibles->take(3) as $alerta)
                <div class="cc-alerta cc-{{ $alerta['nivel'] }}"><span class="cc-punto"></span>{{ $alerta['mensaje'] }}</div>
            @empty
                <div class="cc-alerta cc-verde"><span class="cc-punto"></span>Sin alertas.</div>
            @endforelse
            @if ($alertasVisibles->count() > 3)
                <div class="cc-alerta-mas">y {{ $alertasVisibles->count() - 3 }} más…</div>
            @endif
        </div>
    @else
        <div class="cc-sin-datos">Aún no hay lecturas de esta aplicación.</div>
    @endif

    <div class="cc-tarjeta-pie">
        <span>{{ $estado ? 'Actualizado '.Formato::hace($estado->created_at) : ($app->monitorear ? 'Esperando al recolector' : 'Monitoreo desactivado') }}</span>
        <span class="cc-ver">Ver detalle <x-filament::icon icon="heroicon-m-arrow-right" class="cc-ico-chico" /></span>
    </div>
</a>
