<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Informe extends Model
{
    protected $table = 'informes';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'datos' => 'array',
            'enviado_a' => 'array',
            'completo' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function asunto(): string
    {
        $partes = array_filter([
            $this->rojos ? $this->rojos.' '.($this->rojos === 1 ? 'rojo' : 'rojos') : null,
            $this->amarillos ? $this->amarillos.' '.($this->amarillos === 1 ? 'amarillo' : 'amarillos') : null,
        ]);

        return '[Consola Central] Informe diario '.$this->fecha->format('d/m/Y').' · '.($partes ? implode(' · ', $partes) : 'todo en verde');
    }
}
