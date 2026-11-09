@php use App\Servicios\Monitoreo\Semaforo; @endphp

<div class="cc-dos-columnas">
    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Alertas</h3>
        <div class="cc-alertas">
            @forelse ($estado?->alertas ?? [] as $alerta)
                <div class="cc-alerta cc-alerta-grande cc-{{ $alerta['nivel'] }}"><span class="cc-punto"></span>{{ $alerta['mensaje'] }}</div>
            @empty
                <div class="cc-alerta cc-alerta-grande cc-verde"><span class="cc-punto"></span>Todo en orden. No hay alertas.</div>
            @endforelse
        </div>

        <h3 class="cc-caja-titulo cc-mt">Últimas 24 horas</h3>
        <div class="cc-linea-tiempo" title="Cada bloque son 15 minutos">
            @foreach ($linea_tiempo as $bloque)
                <span class="cc-bloque cc-fondo-{{ $bloque ?? 'vacio' }}"></span>
            @endforeach
        </div>
        <div class="cc-linea-tiempo-pie">
            <span>hace 24 h</span>
            <span>Disponibilidad: <strong>{{ $disponibilidad !== null ? $disponibilidad.' %' : '—' }}</strong></span>
            <span>ahora</span>
        </div>
    </section>

    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Ficha</h3>
        <dl class="cc-ficha">
            <dt>Carpeta</dt><dd><code>{{ $app->ruta() }}</code></dd>
            <dt>Tipo</dt><dd>{{ $app->esFpm() ? 'PHP-FPM (pool '.$app->pool_fpm.')' : 'Octane' }}</dd>
            @if ($app->esOctane())
                <dt>Unidades</dt><dd><code>{{ $app->servicio }}</code> · <code>{{ $app->servicio_cola ?? '—' }}</code> · <code>{{ $app->timer_scheduler ?? '—' }}</code></dd>
                <dt>Puerto interno</dt><dd>{{ $app->puerto_interno }}</dd>
            @endif
            <dt>Puertos públicos</dt><dd>{{ $app->puertos_publicos ?: '—' }}</dd>
            <dt>Dominios</dt><dd>{{ $app->dominios ? str_replace(',', ', ', $app->dominios) : '—' }}</dd>
            <dt>Base de datos</dt><dd>{{ $app->base_datos ?: '—' }}</dd>
            <dt>Sesiones</dt><dd>{{ $app->sesiones_en_bd ? 'En base de datos' : 'En archivos' }}</dd>
            <dt>Arranque</dt><dd>{{ $app->arranque_automatico ? 'Con el servidor' : 'Manual' }}</dd>
            @if ($app->notas)
                <dt>Notas</dt><dd>{{ $app->notas }}</dd>
            @endif
        </dl>
    </section>
</div>

<div class="cc-graficas">
    @foreach ($graficas as $g)
        <section class="cc-caja">
            <div class="cc-grafica-cabeza">
                <h3 class="cc-caja-titulo">{{ $g['titulo'] }}</h3>
                <span class="cc-sis-sub">máx. {{ $g['pico'] }}</span>
            </div>
            @if ($g['trazo'])
                <svg viewBox="0 0 240 48" preserveAspectRatio="none" class="cc-sparkline" role="img" aria-label="{{ $g['titulo'] }} en las últimas 24 horas">
                    <path d="{{ $g['trazo'] }}" />
                </svg>
            @else
                <div class="cc-sin-datos">Aún no hay suficientes lecturas.</div>
            @endif
        </section>
    @endforeach
</div>
