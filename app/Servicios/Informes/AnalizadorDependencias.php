<?php

namespace App\Servicios\Informes;

use App\Models\Aplicacion;
use App\Servicios\Monitoreo\Semaforo;
use Illuminate\Support\Facades\Process;

/**
 * composer audit / composer outdated en la carpeta de cada app (como usuario consola, solo lectura).
 * --no-plugins: nunca se ejecuta código de plugins de la app analizada.
 */
class AnalizadorDependencias
{
    /**
     * @return array{disponible: bool, semaforo: string, total: int, altas: int, avisos: list<array{paquete: string, severidad: ?string, titulo: string, cve: ?string, afecta: ?string}>, error: ?string}
     */
    public function vulnerabilidades(Aplicacion $app): array
    {
        $vacio = ['disponible' => false, 'semaforo' => Semaforo::GRIS, 'total' => 0, 'altas' => 0, 'avisos' => [], 'error' => null];

        if (! is_file($app->ruta().'/composer.lock')) {
            return [...$vacio, 'error' => 'No hay composer.lock'];
        }

        // composer audit sale con código distinto de 0 cuando encuentra avisos: se usa la salida igual
        $json = $this->composer($app, ['audit', '--format=json', '--locked', '--no-dev']);
        if (! is_array($json) || ! array_key_exists('advisories', $json)) {
            return [...$vacio, 'error' => 'composer audit no respondió'];
        }

        $avisos = [];
        foreach ((array) $json['advisories'] as $paquete => $lista) {
            foreach ((array) $lista as $a) {
                $avisos[] = [
                    'paquete' => (string) $paquete,
                    'severidad' => isset($a['severity']) ? strtolower((string) $a['severity']) : null,
                    'titulo' => mb_strimwidth((string) ($a['title'] ?? 'Aviso de seguridad'), 0, 140, '…'),
                    'cve' => $a['cve'] ?? null,
                    'afecta' => isset($a['affectedVersions']) ? mb_strimwidth((string) $a['affectedVersions'], 0, 80, '…') : null,
                ];
            }
        }

        $altas = count(array_filter($avisos, fn ($a) => in_array($a['severidad'], ['high', 'critical'], true)));

        return [
            'disponible' => true,
            'semaforo' => $altas > 0 ? Semaforo::ROJO : ($avisos ? Semaforo::AMARILLO : Semaforo::VERDE),
            'total' => count($avisos),
            'altas' => $altas,
            'avisos' => array_slice($avisos, 0, 15),
            'error' => null,
        ];
    }

    /**
     * @return array{disponible: bool, semaforo: string, mayores: list<array{paquete: string, actual: string, ultima: string}>, menores: int, error: ?string}
     */
    public function desactualizados(Aplicacion $app): array
    {
        $vacio = ['disponible' => false, 'semaforo' => Semaforo::GRIS, 'mayores' => [], 'menores' => 0, 'error' => null];

        if (! is_dir($app->ruta().'/vendor')) {
            return [...$vacio, 'error' => 'No hay carpeta vendor'];
        }

        $json = $this->composer($app, ['outdated', '--direct', '--format=json', '--no-dev']);
        if (! is_array($json) || ! array_key_exists('installed', $json)) {
            return [...$vacio, 'error' => 'composer outdated no respondió'];
        }

        $mayores = [];
        $menores = 0;
        foreach ((array) $json['installed'] as $p) {
            $estado = $p['latest-status'] ?? '';
            if ($estado === 'update-possible') {
                $mayores[] = ['paquete' => (string) $p['name'], 'actual' => (string) ($p['version'] ?? '?'), 'ultima' => (string) ($p['latest'] ?? '?')];
            } elseif ($estado === 'semver-safe-update') {
                $menores++;
            }
        }

        return [
            'disponible' => true,
            'semaforo' => $mayores ? Semaforo::AMARILLO : Semaforo::VERDE,
            'mayores' => array_slice($mayores, 0, 15),
            'menores' => $menores,
            'error' => null,
        ];
    }

    private function composer(Aplicacion $app, array $argumentos): ?array
    {
        try {
            $r = Process::path($app->ruta())
                ->timeout((int) config('consola.correo.timeout_composer', 120))
                ->env([
                    'COMPOSER_HOME' => config('consola.correo.composer_home'),
                    'COMPOSER_NO_INTERACTION' => '1',
                    'COMPOSER_ALLOW_SUPERUSER' => '0',
                ])
                ->run([config('consola.correo.composer', 'composer'), ...$argumentos, '--no-plugins', '--no-interaction']);

            $salida = $r->output();
            $inicio = strpos($salida, '{');

            return $inicio === false ? null : json_decode(substr($salida, $inicio), true);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
