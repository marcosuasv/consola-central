@php use App\Filament\Pages\Seguridad; @endphp

<x-filament-panels::page>
    <div wire:poll.60s class="cc-monitor">
        <x-filament::tabs>
            @foreach (Seguridad::PESTANAS as $clave => $nombre)
                <x-filament::tabs.item :active="$pestana === $clave" wire:click="cambiarPestana('{{ $clave }}')"
                    :icon="match ($clave) { 'bloqueos' => 'heroicon-m-no-symbol', 'historial' => 'heroicon-m-calendar-days', 'visitas' => 'heroicon-m-globe-alt', default => 'heroicon-m-command-line' }">
                    {{ $nombre }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="cc-panel">
            @include('filament.partes.seguridad-'.$pestana)
        </div>
    </div>
</x-filament-panels::page>
