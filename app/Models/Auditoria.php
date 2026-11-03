<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro inmutable de acciones privilegiadas.
 */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    public const UPDATED_AT = null;

    public const MAX_SALIDA = 5120;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La auditoría no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('La auditoría no se puede borrar.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(Aplicacion::class);
    }

    public function exitosa(): bool
    {
        return $this->resultado === 'ok';
    }
}
