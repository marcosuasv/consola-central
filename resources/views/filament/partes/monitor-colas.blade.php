@php use App\Support\Formato; @endphp

<div class="cc-dos-columnas">
    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Pendientes</h3>
        @if ($pendientes === null)
            <div class="cc-sin-datos">Sin datos (la app no tiene tabla <code>jobs</code> o no hay permiso).</div>
        @elseif ($pendientes === [])
            <div class="cc-sin-datos">La cola está vacía.</div>
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

    <section class="cc-caja">
        <h3 class="cc-caja-titulo">Fallidos</h3>
        @if ($fallidos === null)
            <div class="cc-sin-datos">Sin datos (la app no tiene tabla <code>failed_jobs</code> o no hay permiso).</div>
        @elseif ($fallidos === [])
            <div class="cc-sin-datos">No hay jobs fallidos.</div>
        @else
            <div class="cc-tabla-contenedor">
                <table class="cc-tabla">
                    <thead><tr><th>Job</th><th>Fecha</th><th>Error</th></tr></thead>
                    <tbody>
                        @foreach ($fallidos as $j)
                            <tr>
                                <td>{{ $j['job'] }}<br><small class="cc-sis-sub">{{ $j['cola'] }}</small></td>
                                <td title="{{ $j['fecha']->format('d/m/Y H:i:s') }}">{{ Formato::hace($j['fecha']) }}</td>
                                <td class="cc-error-corto">{{ $j['error'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
<p class="cc-nota">Reintentar jobs y reiniciar workers se habilita en el Paso 10 (requiere rol administrador).</p>
