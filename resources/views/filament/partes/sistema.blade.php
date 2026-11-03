@php
    use App\Servicios\Monitoreo\Semaforo;
    use App\Support\Formato;

    $c = config('consola.sistema');
    $ram = $sistema?->porcentajeRam();
    $disco = $sistema?->porcentajeDisco();
    $nivel = fn (?int $v, int $amarillo, int $rojo) => $v === null ? 'gris' : ($v >= $rojo ? 'rojo' : ($v >= $amarillo ? 'amarillo' : 'verde'));
@endphp

<div class="cc-sistema">
    <div class="cc-sis-tarjeta">
        <span class="cc-metrica-etiqueta">Carga (1 / 5 / 15 min)</span>
        <span class="cc-sis-valor">{{ $sistema ? number_format($sistema->carga_1, 2) : '—' }}</span>
        <span class="cc-sis-sub">{{ $sistema ? number_format($sistema->carga_5, 2).' · '.number_format($sistema->carga_15, 2).' · '.$sistema->cpus.' CPU' : 'sin datos' }}</span>
    </div>

    <div class="cc-sis-tarjeta">
        <span class="cc-metrica-etiqueta">Memoria RAM</span>
        <span class="cc-sis-valor">{{ $ram !== null ? $ram.' %' : '—' }}</span>
        <div class="cc-barra"><div class="cc-barra-relleno cc-fondo-{{ $nivel($ram, $c['ram_amarillo'], $c['ram_rojo']) }}" style="width: {{ $ram ?? 0 }}%"></div></div>
        <span class="cc-sis-sub">{{ $sistema ? Formato::bytes($sistema->ram_usada, 1).' de '.Formato::bytes($sistema->ram_total, 1) : 'sin datos' }}</span>
    </div>

    <div class="cc-sis-tarjeta">
        <span class="cc-metrica-etiqueta">Disco /</span>
        <span class="cc-sis-valor">{{ $disco !== null ? $disco.' %' : '—' }}</span>
        <div class="cc-barra"><div class="cc-barra-relleno cc-fondo-{{ $nivel($disco, $c['disco_amarillo'], $c['disco_rojo']) }}" style="width: {{ $disco ?? 0 }}%"></div></div>
        <span class="cc-sis-sub">{{ $sistema ? Formato::bytes($sistema->disco_usado, 1).' de '.Formato::bytes($sistema->disco_total, 1) : 'sin datos' }}</span>
    </div>

    <div class="cc-sis-tarjeta cc-sis-ancha">
        <span class="cc-metrica-etiqueta">Servicios base</span>
        <div class="cc-servicios">
            @forelse ($sistema?->servicios ?? [] as $nombre => $estadoServicio)
                <span class="cc-pill cc-{{ $estadoServicio === 'active' ? 'verde' : 'rojo' }}"><span class="cc-punto"></span>{{ $nombre }}</span>
            @empty
                <span class="cc-sis-sub">sin datos</span>
            @endforelse
            @if ($sistema)
                <span class="cc-pill cc-{{ $sistema->montaje_facturas === null ? 'gris' : ($sistema->montaje_facturas ? 'verde' : 'rojo') }}" title="{{ config('consola.montaje_facturas') }}">
                    <span class="cc-punto"></span>Facturas PDF
                </span>
                @php $cert = $sistema->cert_dias; @endphp
                <span class="cc-pill cc-{{ $cert === null ? 'gris' : ($cert <= $c['cert_dias_rojo'] ? 'rojo' : ($cert <= $c['cert_dias_amarillo'] ? 'amarillo' : 'verde')) }}">
                    <span class="cc-punto"></span>Certificado {{ $cert === null ? '' : '· '.$cert.' días' }}
                </span>
            @endif
        </div>
        @if (! empty($sistema?->alertas))
            <div class="cc-sis-alertas">
                @foreach ($sistema->alertas as $alerta)
                    <div class="cc-alerta cc-{{ $alerta['nivel'] }}"><span class="cc-punto"></span>{{ $alerta['mensaje'] }}</div>
                @endforeach
            </div>
        @endif
    </div>
</div>
