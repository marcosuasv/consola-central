<div class="cc-codigo" x-data="{ copiado: false }">
    <div class="cc-codigo-cabeza">
        <code>{{ $ruta }}</code>
        <button type="button" class="cc-codigo-copiar"
            @click="navigator.clipboard.writeText($refs.texto.innerText).then(() => { copiado = true; setTimeout(() => copiado = false, 1500) })">
            <span x-show="! copiado">Copiar</span><span x-show="copiado" x-cloak>Copiado ✓</span>
        </button>
    </div>
    <pre class="cc-log cc-log-crudo" x-ref="texto">{{ $contenido }}</pre>
</div>
