<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadoApp extends Model
{
    protected $table = 'estados_app';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activo_desde' => 'datetime',
            'alertas' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(Aplicacion::class);
    }

    public function servicioActivo(): bool
    {
        return $this->servicio_estado === 'active';
    }
}
