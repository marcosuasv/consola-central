@php
    use App\Support\Formato;
    $d = $elegida['datos'] ?? null;
    $appElegida = $elegida['app'] ?? null;
    $maxHora = $d ? max([1, ...$d['por_hora']]) : 1;
@endphp

<div class="cc-tabla-contenedor">
    <table class="cc-tabla cc-tabla-clic">
        <thead>
            <tr><th>Aplicación</th><th>Peticiones 24 h</th><th>Desde internet</th><th>IPs públicas</th><th>4xx</th><th>5xx</th><th>Rutas sospechosas</th></tr>
        </thead>
        <tbody>
            @foreach ($resumen as $fila)
                @php $a = $fila['app']; $x = $fila['datos']; @endphp
                <tr wire:click="elegirApp({{ $a->id }})" @class(['cc-fila-activa' => $appElegida?->is($a)]) wire:key="visitas-{{ $a->id }}">
                    <td>
                        <strong>{{ $a->nombre }}</strong>
                        @if ($a->publica_internet) <span class="cc-chip cc-chip-aviso">publicada</span> @endif
                    </td>
                    @if ($x['disponible'])
                        <td>{{ number_format($x['total']) }}</td>
                        <td><strong>{{ number_format($x['publicas']) }}</strong></td>
                        <td>{{ $x['ips_publicas'] }}</td>
                        <td>{{ number_format($x['errores_4xx']) }}</td>
                        <td @class(['cc-texto-rojo' => $x['errores_5xx'] > 0])>{{ number_format($x['errores_5xx']) }}</td>
                        <td @class(['cc-texto-amarillo' => $x['sospechosas'] > 0])>{{ number_format($x['sospechosas']) }}</td>
                    @else
                        <td colspan="6" class="cc-sis-sub">No se pudo leer el log de Nginx ({{ $a->log_nginx ?: 'sin ruta' }})</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($d && $d['disponible'])
    <div class="cc-encabezado-seccion">
        <div>
            <h2>{{ $appElegida->nombre }} · visitas desde internet</h2>
            <p class="cc-nota">
                <code>{{ $appElegida->log_nginx }}</code> · últimas 24 h
                @if ($d['recortado']) · se analizó solo el final del archivo (desde {{ $d['desde'] }}) @endif
            </p>
        </div>
    </div>

    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Peticiones públicas por hora</h3>
        <div class="cc-barras cc-barras-24">
            @foreach ($d['por_hora'] as $i => $n)
                <div class="cc-barras-col" title="{{ $n }} peticiones · hace {{ 24 - $i }} h">
                    <div class="cc-barras-barra" style="height: {{ $n ? max(3, round($n * 100 / $maxHora)) : 0 }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="cc-linea-tiempo-pie"><span>hace 24 h</span><span>ahora</span></div>
    </section>

    <div class="cc-dos-columnas">
        <section class="cc-caja">
            <h3 class="cc-caja-titulo">IPs públicas</h3>
            @if ($d['top_ips'] === [])
                <p class="cc-sin-datos">Sin visitas desde internet en las últimas 24 h.</p>
            @else
                <div class="cc-tabla-contenedor">
                    <table class="cc-tabla">
                        <thead><tr><th>IP</th><th>Peticiones</th><th>4xx</th><th>5xx</th><th>Sospechosas</th><th>Última</th></tr></thead>
                        <tbody>
                            @foreach ($d['top_ips'] as $ip)
                                <tr>
                                    <td>
                                        <code>{{ $ip['ip'] }}</code>
                                        @isset ($bloqueadas[$ip['ip']]) <span class="cc-chip">bloqueada</span> @endisset
                                        @if ($ip['agente']) <br><small class="cc-sis-sub" title="{{ $ip['agente'] }}">{{ mb_strimwidth($ip['agente'], 0, 48, '…') }}</small> @endif
                                    </td>
                                    <td>{{ number_format($ip['peticiones']) }}</td>
                                    <td>{{ $ip['e4xx'] }}</td>
                                    <td @class(['cc-texto-rojo' => $ip['e5xx'] > 0])>{{ $ip['e5xx'] }}</td>
                                    <td @class(['cc-texto-amarillo' => $ip['sospechosas'] > 0])>{{ $ip['sospechosas'] }}</td>
                                    <td title="{{ $ip['ultima'] }}">{{ Formato::hace(\Carbon\Carbon::parse($ip['ultima'])) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="cc-caja">
            <h3 class="cc-caja-titulo">Rutas más pedidas desde internet</h3>
            @forelse ($d['top_rutas'] as $r)
                <div class="cc-fila-dato">
                    <code class="cc-ruta">{{ $r['ruta'] }}</code>
                    <span>
                        @if ($r['sospechosa']) <span class="cc-chip cc-chip-aviso">sospechosa</span> @endif
                        <strong>{{ number_format($r['peticiones']) }}</strong>
                    </span>
                </div>
            @empty
                <p class="cc-sin-datos">Sin datos.</p>
            @endforelse
            <p class="cc-nota cc-mt">«Sospechosa» = rutas típicas de escaneos (<code>.env</code>, <code>wp-login</code>, <code>.git</code>, <code>phpmyadmin</code>…).</p>
        </section>
    </div>
@endif
