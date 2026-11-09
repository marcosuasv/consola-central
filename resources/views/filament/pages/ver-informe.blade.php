<x-filament-panels::page>
    {{-- El HTML del correo se muestra aislado (sin scripts) para que se vea igual que en la bandeja --}}
    <iframe class="cc-informe-marco" sandbox title="Informe diario" srcdoc="{{ $this->record->html }}"></iframe>
</x-filament-panels::page>
