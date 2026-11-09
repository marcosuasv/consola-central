<?php

namespace App\Servicios\Monitoreo;

use App\Models\Aplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consultas de solo lectura a las bases de las apps (conexión `lectura`, usuario consola_ro).
 * Solo se piden las columnas permitidas (AGENTS.md 5.2). Cualquier error regresa null = "sin datos".
 */
class LecturaApps
{
    public const CONEXION = 'lectura';

    private function tabla(Aplicacion $app, string $tabla): ?Builder
    {
        if (blank($app->base_datos) || ! preg_match(Aplicacion::REGEX_BD, $app->base_datos)) {
            return null;
        }

        // Laravel envuelve cada segmento: `pca-gam`.`sessions`
        return DB::connection(self::CONEXION)->table($app->base_datos.'.'.$tabla);
    }

    private function intentar(Aplicacion $app, string $que, callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (\Throwable $e) {
            Log::debug("Consola: sin datos de {$que} en {$app->carpeta}: ".mb_substr($e->getMessage(), 0, 200));

            return null;
        }
    }

    private function limiteSesion(): int
    {
        return CarbonImmutable::now()->subMinutes((int) config('consola.minutos_sesion', 15))->getTimestamp();
    }

    /**
     * @return array{usuarios: int, invitados: int}|null
     */
    public function conteoSesiones(Aplicacion $app): ?array
    {
        if (! $app->sesiones_en_bd) {
            return $this->conteoSesionesArchivo($app);
        }

        return $this->intentar($app, 'sesiones', function () use ($app) {
            $base = $this->tabla($app, 'sessions')?->where('last_activity', '>=', $this->limiteSesion());
            if (! $base) {
                return null;
            }

            return [
                'usuarios' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
                'invitados' => (clone $base)->whereNull('user_id')->count('last_activity'),
            ];
        });
    }

    /**
     * Usuarios conectados con nombre, correo, IP, navegador y última actividad.
     *
     * @return list<array{user_id: ?int, nombre: ?string, correo: ?string, ip: ?string, navegador: string, ultima: CarbonImmutable}>|null
     */
    public function usuariosConectados(Aplicacion $app, int $limite = 200): ?array
    {
        if (! $app->sesiones_en_bd) {
            return $this->usuariosDesdeArchivos($app, $limite);
        }

        return $this->intentar($app, 'usuarios conectados', function () use ($app, $limite) {
            $sesiones = $this->tabla($app, 'sessions')
                ?->select(['user_id', 'ip_address', 'user_agent', 'last_activity'])
                ->where('last_activity', '>=', $this->limiteSesion())
                ->orderByDesc('last_activity')
                ->limit($limite)
                ->get();

            if ($sesiones === null) {
                return null;
            }

            $usuarios = $this->nombresUsuarios($app, $sesiones->pluck('user_id')->filter()->unique()->all());

            return $sesiones->map(fn ($s) => [
                'user_id' => $s->user_id ? (int) $s->user_id : null,
                'nombre' => $usuarios[$s->user_id]['name'] ?? null,
                'correo' => $usuarios[$s->user_id]['email'] ?? null,
                'ip' => $s->ip_address,
                'navegador' => self::navegador($s->user_agent),
                'ultima' => CarbonImmutable::createFromTimestamp((int) $s->last_activity, config('app.timezone')),
            ])->values()->all();
        });
    }

    /** @return array<int, array{name: ?string, email: ?string}> */
    private function nombresUsuarios(Aplicacion $app, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->intentar($app, 'usuarios', fn () => $this->tabla($app, 'users')
            ?->select(['id', 'name', 'email'])
            ->whereIn('id', array_slice($ids, 0, 500))
            ->get()
            ->mapWithKeys(fn ($u) => [$u->id => ['name' => $u->name, 'email' => $u->email]])
            ->all()) ?? [];
    }

    // ---- Sesiones en archivos (intranet) ----

    /** @return list<array{id: ?int, modificado: int}>|null */
    private function sesionesArchivo(Aplicacion $app): ?array
    {
        $carpeta = $app->ruta().'/storage/framework/sessions';
        if (! is_dir($carpeta) || ! is_readable($carpeta)) {
            return null;
        }

        $limite = $this->limiteSesion();
        $maximo = (int) config('consola.limites.archivos_sesion', 3000);
        $resultado = [];
        $revisados = 0;

        foreach (new \DirectoryIterator($carpeta) as $archivo) {
            if (! $archivo->isFile() || str_starts_with($archivo->getFilename(), '.')) {
                continue;
            }
            if (++$revisados > $maximo) {
                break;
            }
            if ($archivo->getMTime() < $limite) {
                continue;
            }

            $contenido = @file_get_contents($archivo->getPathname(), false, null, 0, 64 * 1024);
            $id = ($contenido && preg_match('/login_web_[a-f0-9]{40}";i:(\d+);/', $contenido, $m)) ? (int) $m[1] : null;

            $resultado[] = ['id' => $id, 'modificado' => $archivo->getMTime()];
        }

        return $resultado;
    }

    private function conteoSesionesArchivo(Aplicacion $app): ?array
    {
        $sesiones = $this->sesionesArchivo($app);
        if ($sesiones === null) {
            return null;
        }

        $conId = array_filter(array_column($sesiones, 'id'));

        return [
            'usuarios' => count(array_unique($conId)),
            'invitados' => count($sesiones) - count($conId),
        ];
    }

    private function usuariosDesdeArchivos(Aplicacion $app, int $limite): ?array
    {
        $sesiones = $this->sesionesArchivo($app);
        if ($sesiones === null) {
            return null;
        }

        usort($sesiones, fn ($a, $b) => $b['modificado'] <=> $a['modificado']);
        $sesiones = array_slice(array_values(array_filter($sesiones, fn ($s) => $s['id'] !== null)), 0, $limite);
        $usuarios = $this->nombresUsuarios($app, array_unique(array_column($sesiones, 'id')));

        return array_map(fn ($s) => [
            'user_id' => $s['id'],
            'nombre' => $usuarios[$s['id']]['name'] ?? null,
            'correo' => $usuarios[$s['id']]['email'] ?? null,
            'ip' => null,
            'navegador' => '—',
            'ultima' => CarbonImmutable::createFromTimestamp($s['modificado'], config('app.timezone')),
        ], $sesiones);
    }

    // ---- Colas ----

    public function jobsPendientes(Aplicacion $app): ?int
    {
        return $this->intentar($app, 'jobs', fn () => $this->tabla($app, 'jobs')?->count('id'));
    }

    public function fallidos24h(Aplicacion $app): ?int
    {
        return $this->intentar($app, 'failed_jobs', fn () => $this->tabla($app, 'failed_jobs')
            ?->where('failed_at', '>=', CarbonImmutable::now()->subDay()->format('Y-m-d H:i:s'))
            ->count('id'));
    }

    /**
     * @return list<array{id: int, cola: ?string, job: string, intentos: int, creado: CarbonImmutable, reservado: bool}>|null
     */
    public function listaPendientes(Aplicacion $app, int $limite = 50): ?array
    {
        return $this->intentar($app, 'jobs', fn () => $this->tabla($app, 'jobs')
            ?->select(['id', 'queue', 'attempts', 'reserved_at', 'created_at'])
            ->selectRaw('SUBSTRING(`payload`, 1, 2000) AS payload')
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->map(fn ($j) => [
                'id' => (int) $j->id,
                'cola' => $j->queue,
                'job' => self::nombreJob($j->payload),
                'intentos' => (int) $j->attempts,
                'creado' => CarbonImmutable::createFromTimestamp((int) $j->created_at, config('app.timezone')),
                'reservado' => $j->reserved_at !== null,
            ])->all());
    }

    /**
     * @return list<array{id: int, uuid: ?string, cola: ?string, job: string, fecha: CarbonImmutable, error: string}>|null
     */
    public function listaFallidos(Aplicacion $app, int $limite = 50): ?array
    {
        return $this->intentar($app, 'failed_jobs', fn () => $this->tabla($app, 'failed_jobs')
            // Solo el inicio de payload/exception: pueden medir decenas de KB por fila
            ?->select(['id', 'uuid', 'queue', 'failed_at'])
            ->selectRaw('SUBSTRING(`payload`, 1, 2000) AS payload')
            ->selectRaw('SUBSTRING(`exception`, 1, 6000) AS exception')
            ->orderByDesc('failed_at')
            ->limit($limite)
            ->get()
            ->map(fn ($j) => self::mapearFallido($j))
            ->all());
    }

    public function totalFallidos(Aplicacion $app): ?int
    {
        return $this->intentar($app, 'failed_jobs', fn () => $this->tabla($app, 'failed_jobs')?->count('id'));
    }

    /**
     * @return array{id: int, uuid: ?string, cola: ?string, job: string, fecha: CarbonImmutable, error: string, detalle: string}
     */
    public static function mapearFallido(object $j): array
    {
        $excepcion = LectorLog::ocultarSecretos((string) $j->exception);
        $lineas = preg_split('/\R/', $excepcion);

        return [
            'id' => (int) $j->id,
            'uuid' => $j->uuid ?: null,
            'cola' => $j->queue,
            'job' => self::nombreJob($j->payload),
            'fecha' => CarbonImmutable::parse($j->failed_at, config('app.timezone')),
            'error' => mb_substr($lineas[0] ?? '', 0, 300),
            'detalle' => implode("\n", array_slice($lineas, 0, 25)),
        ];
    }

    // ---- Utilidades ----

    public static function nombreJob(?string $payload): string
    {
        $datos = json_decode((string) $payload, true);
        $nombre = is_array($datos) ? ($datos['displayName'] ?? $datos['job'] ?? null) : null;

        // El payload puede venir recortado (JSON incompleto): se busca displayName directamente
        if (! is_string($nombre) && preg_match('/"displayName"\s*:\s*"((?:[^"\\\\]|\\\\.)+)"/', (string) $payload, $m)) {
            $nombre = stripslashes($m[1]);
        }

        return is_string($nombre) ? class_basename($nombre) : 'desconocido';
    }

    public static function navegador(?string $agente): string
    {
        $agente = (string) $agente;

        $navegador = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'OPR/') => 'Opera',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Chrome/') => 'Chrome',
            str_contains($agente, 'Safari/') => 'Safari',
            $agente === '' => 'Desconocido',
            default => 'Otro',
        };

        $sistema = match (true) {
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'iPhone'), str_contains($agente, 'iPad') => 'iOS',
            str_contains($agente, 'Mac OS') => 'macOS',
            str_contains($agente, 'Linux') => 'Linux',
            default => null,
        };

        return $sistema ? "{$navegador} · {$sistema}" : $navegador;
    }
}
