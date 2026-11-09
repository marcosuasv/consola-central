@if ($f2b['error'])
    <div class="cc-aviso cc-aviso-rojo" role="alert">
        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="cc-ico" />
        <div>
            <strong>No se pudo consultar Fail2ban.</strong> {{ $f2b['error'] }}<br>
            <small>Se consulta con <code>sudo -n consola-ctl f2b status</code>. Revisa que el servicio fail2ban esté activo.</small>
        </div>
    </div>
@elseif ($f2b['reglas'] === [])
    <div class="cc-vacio"><p>Fail2ban no reporta reglas activas.</p></div>
@else
    <div class="cc-reglas">
        @foreach ($f2b['reglas'] as $regla => $d)
            <section class="cc-caja cc-regla" wire:key="regla-{{ $regla }}">
                <div class="cc-grafica-cabeza">
                    <h3 class="cc-caja-titulo"><code>{{ $regla }}</code></h3>
                    <span class="cc-pill cc-{{ $d['actuales'] > 0 ? 'amarillo' : 'verde' }}"><span class="cc-punto"></span>{{ $d['actuales'] }} {{ $d['actuales'] === 1 ? 'bloqueada' : 'bloqueadas' }}</span>
                </div>
                <p class="cc-sis-sub">{{ number_format($d['total']) }} bloqueos desde que arrancó Fail2ban · {{ $d['fallos'] }} {{ $d['fallos'] === 1 ? 'intento' : 'intentos' }} en curso</p>
                @if (! empty($d['error']))
                    <p class="cc-texto-rojo cc-nota">{{ $d['error'] }}</p>
                @elseif ($d['ips'] === [])
                    <p class="cc-sin-datos">Ninguna IP bloqueada en esta regla.</p>
                @else
                    <ul class="cc-ips">
                        @foreach ($d['ips'] as $ip)
                            <li wire:key="ip-{{ $regla }}-{{ $ip }}">
                                <code>{{ $ip }}</code>
                                @if (\App\Servicios\Seguridad\Ip::esPublica($ip))
                                    <span class="cc-chip cc-chip-aviso">internet</span>
                                @else
                                    <span class="cc-chip">red interna</span>
                                @endif
                                <span class="cc-ips-accion">{{ ($this->desbloquearAction)(['regla' => $regla, 'ip' => $ip]) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
    <p class="cc-nota">Se actualiza cada minuto. Desbloquear usa <code>consola-ctl f2b unban</code> y queda en la auditoría.</p>
@endif
