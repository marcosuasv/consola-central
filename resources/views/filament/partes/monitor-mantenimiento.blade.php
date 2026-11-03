<div class="cc-dos-columnas">
    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Versión y soporte</h3>
        <div class="cc-version">
            <span class="cc-foco cc-{{ $soporte['semaforo'] }}"></span>
            <div>
                <div class="cc-kpi-valor">{{ $version ? 'Laravel '.$version : 'Versión desconocida' }}</div>
                <div class="cc-sis-sub">{{ $soporte['texto'] }}</div>
            </div>
        </div>

        <h3 class="cc-caja-titulo cc-mt">Código en el servidor</h3>
        <p>
            @if ($git === null)
                No se pudo leer el estado de git.
            @elseif ($git === 0)
                Sin cambios pendientes: el código coincide con el último commit.
            @else
                <strong>{{ $git }}</strong> archivo(s) con cambios sin commitear.
            @endif
        </p>
        <p class="cc-nota cc-mt">
            El análisis de vulnerabilidades (<code>composer audit</code>) y librerías desactualizadas llega con el informe diario (Paso 12).
        </p>
    </section>

    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Recomendaciones</h3>
        <div class="cc-alertas">
            @forelse ($recomendaciones as $r)
                <div class="cc-alerta cc-alerta-grande cc-{{ $r['nivel'] }}"><span class="cc-punto"></span>{{ $r['texto'] }}</div>
            @empty
                <div class="cc-alerta cc-alerta-grande cc-verde"><span class="cc-punto"></span>No hay recomendaciones por ahora.</div>
            @endforelse
        </div>
    </section>
</div>
