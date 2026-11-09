<?php

namespace App\Servicios\Asistente;

/**
 * Resultado del asistente: archivos para revisar y el script bash que los instala.
 */
final class Paquete
{
    /**
     * @param  list<array{ruta: string, titulo: string, contenido: string}>  $archivos
     * @param  list<string>  $pasosManuales
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $nombreScript,
        public readonly string $script,
        public readonly array $archivos,
        public readonly array $pasosManuales = [],
    ) {}

    public function aArreglo(): array
    {
        return [
            'titulo' => $this->titulo,
            'nombre_script' => $this->nombreScript,
            'script' => $this->script,
            'archivos' => $this->archivos,
            'pasos_manuales' => $this->pasosManuales,
        ];
    }
}
