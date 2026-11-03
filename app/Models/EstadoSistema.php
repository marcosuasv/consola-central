<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoSistema extends Model
{
    protected $table = 'estados_sistema';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'servicios' => 'array',
            'alertas' => 'array',
            'montaje_facturas' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function porcentajeRam(): ?int
    {
        return $this->ram_total ? (int) round($this->ram_usada * 100 / $this->ram_total) : null;
    }

    public function porcentajeDisco(): ?int
    {
        return $this->disco_total ? (int) round($this->disco_usado * 100 / $this->disco_total) : null;
    }
}
