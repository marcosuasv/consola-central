<?php

namespace App\Servicios\Control;

use App\Models\Aplicacion;
use App\Models\Auditoria;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

/**
 * ÚNICA puerta hacia acciones con privilegios: sudo -n /usr/local/sbin/consola-ctl <acción> ...
 *
 * - Siempre arreglo de argumentos (nunca cadenas de shell).
 * - Se valida aquí aunque el script vuelva a validar.
 * - Cada llamada, con éxito o no, queda en la tabla `auditorias`.
 */
class ConsolaCtl
{
    public const BINARIO = '/usr/local/sbin/consola-ctl';

    public const TIMEOUT = 60;

    public const REGEX_UNIDAD = '/^app-[a-z0-9-]+(\.service|\.timer)?$/';

    public const REGEX_CARPETA = '/^[A-Za-z0-9_-]+$/';

    public const ACCIONES_SERVICIO = ['start', 'stop', 'restart', 'reload', 'status', 'enable', 'disable'];

    private ?int $aplicacionId = null;

    /** Copia que registra en la auditoría a qué aplicación pertenece la acción. */
    public function para(?Aplicacion $app): static
    {
        $copia = clone $this;
        $copia->aplicacionId = $app?->id;

        return $copia;
    }

    public function servicio(string $accion, string $unidad): ResultadoCtl
    {
        if (! in_array($accion, self::ACCIONES_SERVICIO, true)) {
            throw new InvalidArgumentException("Acción de servicio no permitida: {$accion}");
        }
        $this->validarUnidad($unidad);

        return $this->ejecutar("servicio.{$accion}", $unidad, ['servicio', $accion, $unidad]);
    }

    public function fpmReload(): ResultadoCtl
    {
        return $this->ejecutar('fpm.reload', 'php8.2-fpm', ['fpm', 'reload']);
    }

    public function colaReiniciar(string $carpeta): ResultadoCtl
    {
        $this->validarCarpeta($carpeta);

        return $this->ejecutar('cola.reiniciar', $carpeta, ['cola', 'reiniciar', $carpeta]);
    }

    public function colaReintentar(string $carpeta, string $id): ResultadoCtl
    {
        $this->validarCarpeta($carpeta);
        if (! preg_match('/^(all|\d{1,12}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $id)) {
            throw new InvalidArgumentException("Identificador de job inválido: {$id}");
        }

        return $this->ejecutar('cola.reintentar', $carpeta, ['cola', 'reintentar', $carpeta, $id]);
    }

    /**
     * Respaldo de una base (Paso 14): root ejecuta mysqldump y escribe en /var/backups/consola/<base>/.
     * Requiere la acción «respaldo» en consola-ctl (ver docs/RESPALDOS.md).
     */
    public function respaldoCrear(string $base): ResultadoCtl
    {
        $this->validarBase($base);

        return $this->ejecutar('respaldo.crear', $base, ['respaldo', 'crear', $base], timeout: (int) config('consola.respaldos.timeout', 1800));
    }

    /** Borra respaldos de más de $dias días, conservando siempre los 3 más recientes. */
    public function respaldoPurgar(string $base, int $dias): ResultadoCtl
    {
        $this->validarBase($base);
        if ($dias < 1 || $dias > 365) {
            throw new InvalidArgumentException("Días de retención inválidos: {$dias}");
        }

        return $this->ejecutar('respaldo.purgar', $base, ['respaldo', 'purgar', $base, (string) $dias]);
    }

    private function validarBase(string $base): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $base)) {
            throw new InvalidArgumentException("Nombre de base inválido: {$base}");
        }
    }

    public const REGEX_REGLA_F2B = '/^[A-Za-z0-9_.-]{1,64}$/';

    /**
     * Estado de Fail2ban (lista de reglas o detalle de una). Es una consulta: solo se audita si falla,
     * para no llenar la auditoría con cada refresco de la pantalla.
     */
    public function f2bStatus(?string $regla = null): ResultadoCtl
    {
        $argumentos = ['f2b', 'status'];
        if ($regla !== null) {
            $this->validarRegla($regla);
            $argumentos[] = $regla;
        }

        return $this->ejecutar('f2b.status', $regla ?? 'fail2ban', $argumentos, auditarExito: false);
    }

    public function f2bUnban(string $regla, string $ip): ResultadoCtl
    {
        $this->validarRegla($regla);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException("IP inválida: {$ip}");
        }

        return $this->ejecutar('f2b.unban', "{$regla} {$ip}", ['f2b', 'unban', $regla, $ip]);
    }

    private function validarRegla(string $regla): void
    {
        if (! preg_match(self::REGEX_REGLA_F2B, $regla)) {
            throw new InvalidArgumentException("Regla de Fail2ban inválida: {$regla}");
        }
    }

    /**
     * @param  list<string>  $argumentos  argumentos para consola-ctl
     */
    private function ejecutar(string $accion, ?string $objetivo, array $argumentos, bool $auditarExito = true, ?int $timeout = null): ResultadoCtl
    {
        try {
            $proceso = Process::timeout($timeout ?? self::TIMEOUT)->run(['sudo', '-n', self::BINARIO, ...$argumentos]);
            $salida = trim($proceso->output()."\n".$proceso->errorOutput());
            $resultado = new ResultadoCtl($proceso->successful(), $proceso->exitCode(), $salida);
        } catch (\Throwable $e) {
            $resultado = new ResultadoCtl(false, null, 'No se pudo ejecutar consola-ctl: '.$e->getMessage());
        }

        if ($resultado->ok && ! $auditarExito) {
            return $resultado;
        }

        Auditoria::create([
            'user_id' => auth()->id(),
            'aplicacion_id' => $this->aplicacionId,
            'accion' => $accion,
            'objetivo' => $objetivo,
            'parametros' => $argumentos,
            'resultado' => $resultado->ok ? 'ok' : 'error',
            'codigo_salida' => $resultado->codigo,
            'salida' => mb_strcut($resultado->salida, 0, Auditoria::MAX_SALIDA),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);

        return $resultado;
    }

    private function validarUnidad(string $unidad): void
    {
        if (! preg_match(self::REGEX_UNIDAD, $unidad)) {
            throw new InvalidArgumentException("Unidad no permitida: {$unidad}");
        }
    }

    private function validarCarpeta(string $carpeta): void
    {
        if (! preg_match(self::REGEX_CARPETA, $carpeta)) {
            throw new InvalidArgumentException("Carpeta inválida: {$carpeta}");
        }
    }
}
