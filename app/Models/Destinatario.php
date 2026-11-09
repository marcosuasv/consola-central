<?php

namespace App\Models;

use App\Servicios\Monitoreo\Semaforo;
use Illuminate\Database\Eloquent\Model;

/**
 * Persona que recibe el informe diario y/o los avisos inmediatos.
 */
class Destinatario extends Model
{
    protected $table = 'destinatarios';

    public const NIVELES = [
        'todo' => 'Todos los días',
        'amarillo' => 'Solo si hay amarillo o rojo',
        'rojo' => 'Solo si hay rojo',
    ];

    protected $fillable = ['nombre', 'correo', 'activo', 'nivel_minimo', 'avisos_inmediatos'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'avisos_inmediatos' => 'boolean'];
    }

    /** ¿Le toca recibir un informe con este semáforo general? */
    public function recibeInforme(string $semaforo): bool
    {
        return $this->activo && match ($this->nivel_minimo) {
            'rojo' => $semaforo === Semaforo::ROJO,
            'amarillo' => in_array($semaforo, [Semaforo::ROJO, Semaforo::AMARILLO], true),
            default => true,
        };
    }
}
