@php
    $maxDia = max([1, ...array_values($resumen['por_dia'])]);
@endphp

<div class="cc-dos-columnas">
    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Bloqueos por día</h3>
        <div class="cc-barras">
            @foreach ($resumen['por_dia'] as $dia => $n)
                <div class="cc-barras-col" title="{{ $n }} bloqueos el {{ \Carbon\Carbon::parse($dia)->locale('es')->translatedFormat('l j') }}">
                    <span class="cc-barras-valor">{{ $n }}</span>
                    <div class="cc-barras-barra" style="height: {{ max(2, round($n * 100 / $maxDia)) }}%"></div>
                    <span class="cc-barras-etiqueta">{{ \Carbon\Carbon::parse($dia)->locale('es')->translatedFormat('D j') }}</span>
                </div>
            @endforeach
        </div>
        <h3 class="cc-caja-titulo cc-mt">Por regla</h3>
        @forelse ($resumen['por_regla'] as $regla => $n)
            <div class="cc-fila-dato"><code>{{ $regla }}</code><strong>{{ $n }}</strong></div>
        @empty
            <p class="cc-sin-datos">Sin bloqueos en los últimos 7 días.</p>
        @endforelse
    </section>

    <section class="cc-caja">
        <h3 class="cc-caja-titulo">IPs más bloqueadas</h3>
        @if ($resumen['ips'] === [])
            <p class="cc-sin-datos">Sin bloqueos en los últimos 7 días.</p>
        @else
            <div class="cc-tabla-contenedor">
                <table class="cc-tabla">
                    <thead><tr><th>IP</th><th>Bloqueos</th><th>Reglas</th><th>Último</th></tr></thead>
                    <tbody>
                        @foreach ($resumen['ips'] as $i)
                            <tr>
                                <td><code>{{ $i['ip'] }}</code></td>
                                <td>{{ $i['bloqueos'] }}</td>
                                <td>{{ implode(', ', $i['reglas']) }}</td>
                                <td title="{{ $i['ultimo'] }}">{{ \App\Support\Formato::hace(\Carbon\Carbon::parse($i['ultimo'])) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<section class="cc-caja">
    <h3 class="cc-caja-titulo">Eventos recientes</h3>
    @if ($resumen['recientes'] === [])
        <p class="cc-sin-datos">No hay eventos (o no se pudo leer <code>/var/log/fail2ban.log</code>).</p>
    @else
        <div class="cc-tabla-contenedor">
            <table class="cc-tabla">
                <thead><tr><th>Fecha</th><th>Evento</th><th>Regla</th><th>IP</th></tr></thead>
                <tbody>
                    @foreach ($resumen['recientes'] as $e)
                        <tr>
                            <td>{{ $e['fecha'] }}</td>
                            <td><span class="cc-pill cc-{{ $e['tipo'] === 'Ban' ? 'rojo' : ($e['tipo'] === 'Unban' ? 'verde' : 'gris') }}"><span class="cc-punto"></span>{{ ['Ban' => 'Bloqueo', 'Unban' => 'Desbloqueo', 'Restore Ban' => 'Bloqueo restaurado'][$e['tipo']] ?? $e['tipo'] }}</span></td>
                            <td><code>{{ $e['regla'] }}</code></td>
                            <td><code>{{ $e['ip'] }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p class="cc-nota">Fuente: <code>/var/log/fail2ban.log</code> (y <code>.1</code>), últimos 7 días.</p>
</section>
