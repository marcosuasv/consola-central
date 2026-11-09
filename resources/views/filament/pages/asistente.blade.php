<x-filament-panels::page>
    <form wire:submit="generar" class="cc-asistente-form">
        {{ $this->form }}
    </form>

    @if ($paquete)
        <div class="cc-monitor" id="resultado" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })" wire:key="paquete-{{ md5($paquete['script']) }}">
            <x-filament::section icon="heroicon-o-document-check" icon-color="success">
                <x-slot name="heading">{{ $paquete['titulo'] }}</x-slot>
                <x-slot name="description">Revisa los archivos y el script. Nada se ha ejecutado en el servidor.</x-slot>
                <x-slot name="afterHeader">
                    <div class="cc-barra-cola-acciones">
                        {{ $this->reiniciarAction }}
                        {{ $this->crearFichaAction }}
                        {{ $this->descargarAction }}
                    </div>
                </x-slot>

                <div class="cc-pasos-asistente">
                    <h3 class="cc-caja-titulo">Cómo aplicarlo</h3>
                    <ol>
                        <li>Descarga <code>{{ $paquete['nombre_script'] }}</code> y revísalo completo.</li>
                        <li>Como root: <code>bash {{ $paquete['nombre_script'] }}</code></li>
                        @foreach ($paquete['pasos_manuales'] as $paso)
                            <li>{{ $paso }}</li>
                        @endforeach
                    </ol>
                </div>
            </x-filament::section>

            <div x-data="{ actual: 'script' }" class="cc-panel">
                <div class="cc-segmentado" role="tablist">
                    <button type="button" @click="actual = 'script'" :class="{ 'cc-activo': actual === 'script' }">Script completo</button>
                    @foreach ($paquete['archivos'] as $i => $archivo)
                        <button type="button" @click="actual = 'a{{ $i }}'" :class="{ 'cc-activo': actual === 'a{{ $i }}' }">{{ $archivo['titulo'] }}</button>
                    @endforeach
                </div>

                <div x-show="actual === 'script'">
                    @include('filament.partes.asistente-codigo', ['ruta' => $paquete['nombre_script'], 'contenido' => $paquete['script']])
                </div>
                @foreach ($paquete['archivos'] as $i => $archivo)
                    <div x-show="actual === 'a{{ $i }}'" x-cloak>
                        @include('filament.partes.asistente-codigo', ['ruta' => $archivo['ruta'], 'contenido' => $archivo['contenido']])
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
