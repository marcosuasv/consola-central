@php use App\Support\Formato; @endphp

@if ($usuarios === null)
    <div class="cc-vacio">
        <x-filament::icon icon="heroicon-o-circle-stack" class="cc-ico-grande" />
        <p>Sin datos de sesiones para esta app.</p>
        <small>Revisa que el usuario <code>consola_ro</code> tenga permiso de lectura en <code>{{ $app->base_datos }}.sessions</code>.</small>
    </div>
@elseif (count($usuarios) === 0)
    <div class="cc-vacio">
        <x-filament::icon icon="heroicon-o-user-group" class="cc-ico-grande" />
        <p>Nadie ha usado la app en los últimos {{ config('consola.minutos_sesion') }} minutos.</p>
    </div>
@else
    <p class="cc-nota">Sesiones con actividad en los últimos {{ config('consola.minutos_sesion') }} minutos.</p>
    <div class="cc-tabla-contenedor">
        <table class="cc-tabla">
            <thead>
                <tr><th>Usuario</th><th>Correo</th><th>IP</th><th>Navegador</th><th>Última actividad</th></tr>
            </thead>
            <tbody>
                @foreach ($usuarios as $u)
                    <tr>
                        <td>
                            <span class="cc-avatar">{{ mb_strtoupper(mb_substr($u['nombre'] ?? '?', 0, 1)) }}</span>
                            {{ $u['nombre'] ?? ($u['user_id'] ? 'Usuario #'.$u['user_id'] : 'Sin iniciar sesión') }}
                        </td>
                        <td>{{ $u['correo'] ?? '—' }}</td>
                        <td><code>{{ $u['ip'] ?? '—' }}</code></td>
                        <td>{{ $u['navegador'] }}</td>
                        <td title="{{ $u['ultima']->format('d/m/Y H:i:s') }}">{{ Formato::hace($u['ultima']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
