<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Aplicación Laravel del servidor vigilada por la consola.
 *
 * @property string $nombre
 * @property string $carpeta
 * @property string $tipo
 */
class Aplicacion extends Model
{
    protected $table = 'aplicaciones';

    public const TIPO_OCTANE = 'octane';

    public const TIPO_FPM = 'fpm';

    public const TIPOS = [
        self::TIPO_OCTANE => 'Octane (servicio systemd)',
        self::TIPO_FPM => 'PHP-FPM (pool)',
    ];

    /** Carpeta base donde viven todas las apps. */
    public const RUTA_BASE = '/var/www/html';

    // Expresiones regulares compartidas por el formulario y los servicios que llaman a consola-ctl.
    public const REGEX_CARPETA = '/^[A-Za-z0-9_-]+$/';

    public const REGEX_SERVICIO = '/^app-[a-z0-9-]+(\.service)?$/';

    public const REGEX_TIMER = '/^app-[a-z0-9-]+\.timer$/';

    public const REGEX_POOL = '/^[a-z0-9_-]+$/';

    public const REGEX_PUERTOS = '/^\d{1,5}(,\d{1,5})*$/';

    public const REGEX_BD = '/^[A-Za-z0-9_-]+$/';

    public const REGEX_URL_SALUD = '#^https?://(127\.0\.0\.1|localhost)(:\d{1,5})?(/.*)?$#';

    public const REGEX_DOMINIO = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    public const REGEX_LOG_NGINX = '#^/var/log/nginx/[A-Za-z0-9._-]+\.log$#';

    protected $fillable = [
        'nombre',
        'carpeta',
        'tipo',
        'servicio',
        'servicio_cola',
        'timer_scheduler',
        'pool_fpm',
        'puerto_interno',
        'puertos_publicos',
        'url',
        'dominios',
        'url_salud',
        'base_datos',
        'usuario_bd',
        'sesiones_en_bd',
        'log_nginx',
        'es_pruebas',
        'arranque_automatico',
        'publica_internet',
        'monitorear',
        'orden',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'puerto_interno' => 'integer',
            'sesiones_en_bd' => 'boolean',
            'es_pruebas' => 'boolean',
            'arranque_automatico' => 'boolean',
            'publica_internet' => 'boolean',
            'monitorear' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function esOctane(): bool
    {
        return $this->tipo === self::TIPO_OCTANE;
    }

    public function esFpm(): bool
    {
        return $this->tipo === self::TIPO_FPM;
    }

    /** Ruta absoluta de la app en el servidor. */
    public function ruta(): string
    {
        return self::RUTA_BASE.'/'.$this->carpeta;
    }

    /** Puertos públicos como arreglo de enteros. */
    public function listaPuertosPublicos(): array
    {
        if (blank($this->puertos_publicos)) {
            return [];
        }

        return array_map('intval', explode(',', $this->puertos_publicos));
    }

    public function estados(): HasMany
    {
        return $this->hasMany(EstadoApp::class);
    }

    public function ultimoEstado(): HasOne
    {
        return $this->hasOne(EstadoApp::class)->latestOfMany();
    }

    /** Carpeta de logs de Laravel de la app. */
    public function rutaLogs(): string
    {
        return $this->ruta().'/storage/logs';
    }

    /** @return list<string> */
    public function listaDominios(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->dominios))));
    }

    /** Parte de los nombres de unidad: app-<clave>. Se deduce del servicio o de la carpeta. */
    public function clave(): string
    {
        if ($this->servicio && preg_match('/^app-([a-z0-9-]+?)(\.service)?$/', $this->servicio, $m)) {
            return $m[1];
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($this->carpeta)), '-');
    }

    public function scopeMonitoreadas(Builder $query): Builder
    {
        return $query->where('monitorear', true);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
