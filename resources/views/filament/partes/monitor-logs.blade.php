@php use App\Filament\Resources\Aplicaciones\Pages\MonitorAplicacion; @endphp

<div class="cc-filtros">
    <div class="cc-segmentado" role="tablist">
        @foreach (MonitorAplicacion::FUENTES_LOG as $clave => $nombre)
            @continue($clave === 'cola' && ! $app->servicio_cola)
            <button type="button" wire:click="$set('fuente', '{{ $clave }}')" @class(['cc-activo' => $fuente === $clave])>{{ $nombre }}</button>
        @endforeach
    </div>

    <div class="cc-filtros-derecha">
        @if ($fuente === 'laravel')
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="nivel" aria-label="Nivel">
                    <option value="">Todos los niveles</option>
                    <option value="WARNING">Advertencias y errores</option>
                    <option value="ERROR">Solo errores</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input type="search" wire:model.live.debounce.500ms="buscar" placeholder="Buscar en el log" maxlength="100" />
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="lineas" aria-label="Líneas">
                <option value="100">100 líneas</option>
                <option value="200">200 líneas</option>
                <option value="500">500 líneas</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <label class="cc-interruptor">
            <input type="checkbox" wire:model.live="enVivo">
            <span>@if ($enVivo)<span class="cc-en-vivo"></span>@endif En vivo</span>
        </label>
    </div>
</div>

<p class="cc-nota">
    <code>{{ $ruta_log ?? 'sin archivo' }}</code> · más reciente arriba · los posibles secretos se ocultan.
</p>

@if ($fuente === 'laravel' && $entradas !== null)
    @if ($entradas === [])
        <div class="cc-vacio"><p>No hay entradas que coincidan.</p></div>
    @else
        <div class="cc-log">
            @foreach ($entradas as $e)
                <details @class(['cc-log-entrada', 'cc-log-'.strtolower($e['nivel'])]) @if (substr_count($e['texto'], "\n") === 0) open @endif>
                    <summary>
                        <span class="cc-log-nivel">{{ $e['nivel'] }}</span>
                        <span class="cc-log-fecha">{{ $e['fecha'] }}</span>
                        <span class="cc-log-texto">{{ mb_strimwidth(strtok($e['texto'], "\n"), 0, 220, '…') }}</span>
                    </summary>
                    @if (substr_count($e['texto'], "\n") > 0)
                        <pre>{{ $e['texto'] }}</pre>
                    @endif
                </details>
            @endforeach
        </div>
    @endif
@elseif ($crudas !== null)
    @if ($crudas === [])
        <div class="cc-vacio"><p>No hay líneas que coincidan.</p></div>
    @else
        <pre class="cc-log cc-log-crudo">@foreach ($crudas as $linea){{ $linea }}
@endforeach</pre>
    @endif
@else
    <div class="cc-vacio">
        <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="cc-ico-grande" />
        <p>No se pudo leer este log.</p>
        <small>El archivo no existe o el usuario <code>consola</code> no tiene permiso de lectura.</small>
    </div>
@endif
