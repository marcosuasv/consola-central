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
     * @param  list<string>  $argumentos  argumentos para consola-ctl
     */
    private function ejecutar(string $accion, ?string $objetivo, array $argumentos): ResultadoCtl
    {
        try {
            $proceso = Process::timeout(self::TIMEOUT)->run(['sudo', '-n', self::BINARIO, ...$argumentos]);
            $salida = trim($proceso->output()."\n".$proceso->errorOutput());
            $resultado = new ResultadoCtl($proceso->successful(), $proceso->exitCode(), $salida);
        } catch (\Throwable $e) {
            $resultado = new ResultadoCtl(false, null, 'No se pudo ejecutar consola-ctl: '.$e->getMessage());
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
