@php $rechazos = collect($entradas)->where('rechazado', true)->count(); @endphp

@if ($rechazos > 0)
    <div class="cc-aviso cc-aviso-ambar" role="status">
        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="cc-ico" />
        <div><strong>{{ $rechazos }} {{ $rechazos === 1 ? 'acción rechazada' : 'acciones rechazadas' }}</strong> en las últimas entradas. Un rechazo significa que alguien pidió a consola-ctl algo fuera de lo permitido.</div>
    </div>
@endif

@if ($entradas === [])
    <div class="cc-vacio">
        <x-filament::icon icon="heroicon-o-command-line" class="cc-ico-grande" />
        <p>Sin entradas en <code>/var/log/consola-ctl.log</code>.</p>
        <small>Puede que el archivo no exista todavía o que el usuario consola no tenga permiso de lectura.</small>
    </div>
@else
    <div class="cc-tabla-contenedor">
        <table class="cc-tabla">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Resultado</th><th>Detalle</th></tr></thead>
            <tbody>
                @foreach ($entradas as $e)
                    <tr @class(['cc-fila-alerta' => $e['rechazado']])>
                        <td>{{ $e['fecha'] }}</td>
                        <td><code>{{ $e['usuario'] }}</code></td>
                        <td>
                            <span class="cc-pill cc-{{ $e['rechazado'] ? 'rojo' : 'verde' }}"><span class="cc-punto"></span>{{ $e['rechazado'] ? 'Rechazado' : 'Permitido' }}</span>
                        </td>
                        <td class="cc-error-corto">{{ $e['mensaje'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="cc-nota">Fuente: <code>/var/log/consola-ctl.log</code> (últimas 200 entradas, más reciente arriba).</p>
@endif
