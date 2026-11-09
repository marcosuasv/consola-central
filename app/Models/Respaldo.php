<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Respaldo extends Model
{
    protected $table = 'respaldos';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'tamano_bytes' => 'integer',
            'duracion_ms' => 'integer',
        ];
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(Aplicacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exitoso(): bool
    {
        return $this->resultado === 'ok';
    }
}
