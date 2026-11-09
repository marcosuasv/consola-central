@php use App\Support\Formato; @endphp

<x-filament-panels::page>
    {{ $this->table }}

    <x-filament::section icon="heroicon-o-clock" :collapsible="true">
        <x-slot name="heading">Historial reciente</x-slot>
        <x-slot name="description">Últimos 30 respaldos (programados y manuales). El detalle de cada llamada a consola-ctl está en Auditoría.</x-slot>

        @if ($historial->isEmpty())
            <div class="cc-vacio">
                <x-filament::icon icon="heroicon-o-archive-box" class="cc-ico-grande" />
                <p>Todavía no hay respaldos.</p>
                <small>Instala la acción «respaldo» en consola-ctl y el timer <code>consola-respaldos.timer</code> (ver <code>docs/RESPALDOS.md</code>).</small>
            </div>
        @else
            <div class="cc-tabla-contenedor">
                <table class="cc-tabla">
                    <thead><tr><th>Fecha</th><th>Base</th><th>Resultado</th><th>Tamaño</th><th>Duración</th><th>Origen</th><th>Archivo / mensaje</th></tr></thead>
                    <tbody>
                        @foreach ($historial as $r)
                            <tr @class(['cc-fila-alerta' => ! $r->exitoso()])>
                                <td title="{{ $r->created_at->format('d/m/Y H:i:s') }}">{{ $r->created_at->format('d/m H:i') }}</td>
                                <td><strong>{{ $r->base_datos }}</strong>@if ($r->aplicacion)<br><small class="cc-sis-sub">{{ $r->aplicacion->nombre }}</small>@endif</td>
                                <td><span class="cc-pill cc-{{ $r->exitoso() ? 'verde' : 'rojo' }}"><span class="cc-punto"></span>{{ $r->exitoso() ? 'Correcto' : 'Error' }}</span></td>
                                <td>{{ Formato::bytes($r->tamano_bytes, 1) }}</td>
                                <td>{{ $r->duracion_ms !== null ? Formato::milisegundos($r->duracion_ms) : '—' }}</td>
                                <td>{{ $r->origen === 'manual' ? 'Manual'.($r->user ? ' · '.$r->user->name : '') : 'Programado' }}</td>
                                <td class="cc-error-corto" title="{{ $r->exitoso() ? $r->archivo.' · SHA-256 '.$r->sha256 : $r->mensaje }}">{{ $r->exitoso() ? basename((string) $r->archivo) : mb_strimwidth((string) $r->mensaje, 0, 200, '…') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
