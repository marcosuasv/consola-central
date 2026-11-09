<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodo en que una app (o el servidor, si aplicacion_id es null) estuvo en rojo.
 */
class Incidente extends Model
{
    protected $table = 'incidentes';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'avisado_en' => 'datetime',
            'recuperacion_avisada_en' => 'datetime',
            'motivos' => 'array',
        ];
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(Aplicacion::class);
    }

    public function scopeAbiertos(Builder $q): Builder
    {
        return $q->whereNull('fin');
    }

    public function nombre(): string
    {
        return $this->aplicacion_id ? ($this->aplicacion?->nombre ?? 'App eliminada') : 'Servidor DebWebServ';
    }

    public function duracionMinutos(): int
    {
        return (int) round($this->inicio->diffInSeconds($this->fin ?? now()) / 60);
    }
}
