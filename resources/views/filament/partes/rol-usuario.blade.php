@php $usuario = auth()->user(); @endphp
@if ($usuario)
    <span @class(['cc-rol', 'cc-rol-admin' => $usuario->esAdmin()]) title="{{ $usuario->tieneMfa() ? 'Verificación en dos pasos activa' : 'Sin verificación en dos pasos' }}">
        <x-filament::icon :icon="$usuario->tieneMfa() ? 'heroicon-m-shield-check' : 'heroicon-m-shield-exclamation'" class="cc-ico-chico" />
        {{ \App\Models\User::ROLES[$usuario->rol] ?? $usuario->rol }}
    </span>
@endif
