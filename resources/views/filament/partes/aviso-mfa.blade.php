@php $usuario = auth()->user(); @endphp
@if ($usuario?->esAdmin() && ! $usuario->tieneMfa() && config('consola.mfa_admin', true))
    <div class="cc-aviso cc-aviso-ambar cc-aviso-mfa" role="status">
        <x-filament::icon icon="heroicon-o-shield-exclamation" class="cc-ico" />
        <div>
            <strong>Activa la verificación en dos pasos.</strong>
            Como administrador, los botones de control (encender, apagar, reiniciar) están bloqueados hasta que la actives.
        </div>
        <x-filament::button tag="a" size="sm" color="warning" :href="filament()->getProfileUrl()" icon="heroicon-m-key">
            Activar ahora
        </x-filament::button>
    </div>
@endif
