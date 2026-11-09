<?php

namespace App\Servicios\Informes;

use App\Models\Aplicacion;
use App\Models\EstadoSistema;
use App\Models\Incidente;
use App\Models\Informe;
use App\Models\Respaldo;
use App\Servicios\Monitoreo\AnalizadorMantenimiento;
use App\Servicios\Monitoreo\Semaforo;
use App\Servicios\Seguridad\AnalizadorNginx;
use App\Servicios\Seguridad\Fail2ban;
use App\Servicios\Seguridad\RegistroConsolaCtl;
use App\Support\Formato;
use Carbon\CarbonImmutable;

/**
 * Paso 12: arma el informe diario con reglas locales (sin IA) y lo guarda en `informes`.
 * Por app: operación 24 h, ciberseguridad, mantenimiento y respaldo; además servidor y recomendaciones.
 */
class GeneradorInforme
{
    public function __construct(
        private AnalizadorDependencias $dependencias,
        private AnalizadorMantenimiento $mantenimiento,
        private AnalizadorNginx $nginx,
        private Fail2ban $fail2ban,
        private RegistroConsolaCtl $registroCtl,
    ) {}

    /**
     * @param  bool  $completo  false = sin composer audit/outdated (rápido, para pruebas desde la web)
     */
    public function generar(bool $completo = true): Informe
    {
        $hasta = CarbonImmutable::now();
        $desde = $hasta->subDay();
        $recomendaciones = [];
        $apps = [];

        foreach (Aplicacion::where('monitorear', true)->ordenadas()->get() as $app) {
            $fila = [
                'nombre' => $app->nombre,
                'detalle' => ($app->esFpm() ? 'PHP-FPM' : 'Octane').' · '.($app->publica_internet ? 'internet' : 'red interna').($app->es_pruebas ? ' · pruebas' : ''),
                'publica' => (bool) $app->publica_internet,
                'operacion' => $this->operacion($app, $desde, $recomendaciones),
                'seguridad' => $this->seguridad($app, $completo, $recomendaciones),
                'mantenimiento' => $this->mantenimientoDe($app, $completo, $recomendaciones),
                'respaldo' => $this->respaldo($app, $recomendaciones),
            ];
            $apps[] = $fila;
        }

        $servidor = $this->servidor($desde, $recomendaciones);

        $celdas = [];
        foreach ($apps as $a) {
            foreach (['operacion', 'seguridad', 'mantenimiento', 'respaldo'] as $col) {
                $celdas[] = $a[$col]['semaforo'];
            }
        }
        $celdas[] = $servidor['semaforo'];

        $orden = [Semaforo::ROJO => 0, Semaforo::AMARILLO => 1, Semaforo::GRIS => 2];
        usort($recomendaciones, fn ($a, $b) => ($orden[$a['nivel']] ?? 3) <=> ($orden[$b['nivel']] ?? 3));

        $datos = [
            'fecha' => $hasta->format('d/m/Y'),
            'desde' => $desde->format('d/m/Y H:i'),
            'hasta' => $hasta->format('d/m/Y H:i'),
            'completo' => $completo,
            'apps' => $apps,
            'servidor' => $servidor,
            'recomendaciones' => array_slice($recomendaciones, 0, 25),
            'urgentes' => array_values(array_map(
                fn ($r) => $r['sujeto'].': '.$r['texto'],
                array_slice(array_filter($recomendaciones, fn ($r) => $r['nivel'] === Semaforo::ROJO), 0, 4),
            )),
        ];

        $informe = Informe::create([
            'fecha' => $hasta->toDateString(),
            'semaforo' => Semaforo::peor(Semaforo::VERDE, ...$celdas),
            'rojos' => count(array_keys($celdas, Semaforo::ROJO, true)),
            'amarillos' => count(array_keys($celdas, Semaforo::AMARILLO, true)),
            'datos' => $datos,
            'html' => view('correo.informe', ['d' => $datos])->render(),
            'completo' => $completo,
            'created_at' => now(),
        ]);

        Informe::where('created_at', '<', now()->subDays((int) config('consola.correo.dias_informes', 90)))->delete();

        return $informe;
    }

    private function celda(string $semaforo, string $texto): array
    {
        return ['semaforo' => $semaforo, 'texto' => $texto];
    }

    private function recomendar(array &$lista, string $nivel, string $sujeto, string $texto): void
    {
        $lista[] = ['nivel' => $nivel, 'sujeto' => $sujeto, 'texto' => $texto];
    }

    private function operacion(Aplicacion $app, CarbonImmutable $desde, array &$rec): array
    {
        $lecturas = $app->estados()->where('created_at', '>=', $desde)
            ->get(['semaforo', 'latencia_ms', 'usuarios_conectados']);
        $ultimo = $app->ultimoEstado()->first();
        $validas = $lecturas->where('semaforo', '!=', Semaforo::GRIS);

        if ($ultimo === null || $validas->isEmpty()) {
            return $this->celda(Semaforo::GRIS, $app->es_pruebas ? 'Entorno de pruebas apagado' : 'Sin lecturas en 24 h');
        }

        $disponible = round($validas->where('semaforo', '!=', Semaforo::ROJO)->count() * 100 / $validas->count(), 1);
        $incidentes = Incidente::where('aplicacion_id', $app->id)
            ->where(fn ($q) => $q->where('inicio', '>=', $desde)->orWhereNull('fin'))->get();
        $minutosCaida = $incidentes->sum(fn (Incidente $i) => $i->duracionMinutos());
        $umbral = config('consola.umbrales');

        $partes = ['Disponible '.rtrim(rtrim(number_format($disponible, 1), '0'), '.').' %'];
        $semaforo = $ultimo->semaforo === Semaforo::GRIS ? Semaforo::VERDE : $ultimo->semaforo;

        if ($incidentes->isNotEmpty() && ! $app->es_pruebas) {
            $n = $incidentes->count();
            $semaforo = Semaforo::ROJO;
            $partes[] = $n.' '.($n === 1 ? 'caída' : 'caídas')." ({$minutosCaida} min)";
            $this->recomendar($rec, Semaforo::ROJO, $app->nombre, "estuvo en falla {$n} ".($n === 1 ? 'vez' : 'veces')." ({$minutosCaida} min en total). Revisar el journal de systemd y el log de la app en esos horarios.");
        }
        if (($ultimo->latencia_ms ?? 0) > $umbral['latencia_ms']) {
            $partes[] = 'respuesta lenta '.Formato::milisegundos($ultimo->latencia_ms);
            $this->recomendar($rec, Semaforo::AMARILLO, $app->nombre, 'responde lento ('.Formato::milisegundos($ultimo->latencia_ms).'). Revisar consultas pesadas y memoria.');
        }
        if (($ultimo->errores_log_24h ?? 0) > 0) {
            $partes[] = $ultimo->errores_log_24h.' '.($ultimo->errores_log_24h === 1 ? 'error' : 'errores').' en el log';
            $this->recomendar($rec, Semaforo::AMARILLO, $app->nombre, "{$ultimo->errores_log_24h} errores en laravel.log. Revisar la pestaña Logs y corregir el más frecuente.");
        }
        if (($ultimo->jobs_fallidos_24h ?? 0) > 0) {
            $partes[] = $ultimo->jobs_fallidos_24h.' jobs fallidos';
            $this->recomendar($rec, ($ultimo->jobs_fallidos_24h > $umbral['fallidos_24h'] && ! $app->es_pruebas) ? Semaforo::ROJO : Semaforo::AMARILLO, $app->nombre, "{$ultimo->jobs_fallidos_24h} jobs fallidos en 24 h. Revisar la pestaña Colas.");
        }
        if (($ultimo->reinicios_24h ?? 0) > 0) {
            $partes[] = $ultimo->reinicios_24h.' '.($ultimo->reinicios_24h === 1 ? 'reinicio' : 'reinicios');
        }
        $pico = (int) $lecturas->max('usuarios_conectados');
        if ($pico > 0) {
            $partes[] = "pico de {$pico} ".($pico === 1 ? 'usuario' : 'usuarios');
        }

        // Un entorno de pruebas nunca cuenta como rojo en el informe
        if ($app->es_pruebas && $semaforo === Semaforo::ROJO) {
            $semaforo = Semaforo::AMARILLO;
        }

        return $this->celda($semaforo, implode(' · ', $partes));
    }

    private function seguridad(Aplicacion $app, bool $completo, array &$rec): array
    {
        $partes = [];
        $semaforo = Semaforo::GRIS;

        if ($completo) {
            $v = $this->dependencias->vulnerabilidades($app);
            $semaforo = $v['semaforo'];
            if (! $v['disponible']) {
                $partes[] = 'Sin datos de vulnerabilidades'.($v['error'] ? " ({$v['error']})" : '');
            } elseif ($v['total'] === 0) {
                $partes[] = '0 vulnerabilidades';
            } else {
                $partes[] = $v['total'].' '.($v['total'] === 1 ? 'vulnerabilidad' : 'vulnerabilidades').($v['altas'] ? " ({$v['altas']} altas)" : '');
                $paquetes = array_values(array_unique(array_column($v['avisos'], 'paquete')));
                $this->recomendar($rec, $v['semaforo'], $app->nombre,
                    'actualizar '.implode(', ', array_slice($paquetes, 0, 5)).(count($paquetes) > 5 ? '…' : '')
                    ." por {$v['total']} ".($v['total'] === 1 ? 'aviso' : 'avisos').' de seguridad'
                    .($app->publica_internet ? '. Prioridad: está publicada a internet.' : '.'));
            }
        } else {
            $partes[] = 'Vulnerabilidades: no analizadas';
        }

        if ($app->publica_internet) {
            $visitas = $this->nginx->analizar($app);
            if ($visitas['disponible']) {
                $partes[] = number_format($visitas['sospechosas']).' visitas sospechosas de '.number_format($visitas['ips_publicas']).' IPs públicas';
            }
        }

        return $this->celda($semaforo, implode(' · ', $partes));
    }

    private function mantenimientoDe(Aplicacion $app, bool $completo, array &$rec): array
    {
        $version = $this->mantenimiento->versionLaravel($app);
        $soporte = $this->mantenimiento->soporteLaravel($version);
        $semaforo = $soporte['semaforo'];
        $partes = [$version ? 'Laravel '.$version : 'Versión de Laravel desconocida'];

        if ($soporte['estado'] === 'sin_soporte') {
            $partes[] = 'sin soporte';
            $this->recomendar($rec, Semaforo::ROJO, $app->nombre, $soporte['texto'].' Planear la migración a una versión con soporte.');
        } elseif ($soporte['estado'] === 'solo_seguridad') {
            $partes[] = 'solo parches de seguridad';
            $this->recomendar($rec, Semaforo::AMARILLO, $app->nombre, $soporte['texto'].' Programar la actualización a la siguiente versión mayor.');
        }

        if ($completo) {
            $o = $this->dependencias->desactualizados($app);
            if ($o['disponible'] && $o['mayores']) {
                $n = count($o['mayores']);
                $semaforo = Semaforo::peor($semaforo === Semaforo::GRIS ? Semaforo::VERDE : $semaforo, Semaforo::AMARILLO);
                $partes[] = $n.' '.($n === 1 ? 'paquete' : 'paquetes').' con versión mayor nueva';
                $ejemplos = array_map(fn ($p) => "{$p['paquete']} {$p['actual']} → {$p['ultima']}", array_slice($o['mayores'], 0, 3));
                $this->recomendar($rec, Semaforo::AMARILLO, $app->nombre, 'librerías con versión mayor disponible: '.implode('; ', $ejemplos).($n > 3 ? '; y '.($n - 3).' más.' : '.'));
            } elseif ($o['disponible']) {
                $partes[] = 'librerías al día';
            }
        }

        $git = $this->mantenimiento->cambiosGit($app);
        if ($git) {
            $partes[] = "{$git} archivos sin commitear";
            if ($app->carpeta !== 'akoc-control') {
                $this->recomendar($rec, Semaforo::GRIS, $app->nombre, "{$git} archivos sin commitear en el servidor: subirlos al repositorio o descartarlos.");
            }
        }

        return $this->celda($semaforo, implode(' · ', $partes));
    }

    private function respaldo(Aplicacion $app, array &$rec): array
    {
        if (! $app->respaldo_activo || blank($app->base_datos)) {
            return $this->celda(Semaforo::GRIS, 'No se respalda');
        }
        if (! Respaldo::where('base_datos', $app->base_datos)->exists()) {
            return $this->celda(Semaforo::GRIS, 'Respaldos sin instalar');
        }

        $limite = now()->subHours((int) config('consola.respaldos.horas_alerta', 26));
        $ok = Respaldo::where('base_datos', $app->base_datos)->where('resultado', 'ok')->latest('created_at')->first();

        if ($ok && $ok->created_at->gte($limite)) {
            return $this->celda(Semaforo::VERDE, $ok->created_at->format('H:i').' · '.Formato::bytes($ok->tamano_bytes, 1));
        }

        $this->recomendar($rec, Semaforo::ROJO, $app->nombre, 'no tiene un respaldo correcto de su base en las últimas 26 h. Revisar Consola → Respaldos y respaldar manualmente.');

        return $this->celda(Semaforo::ROJO, $ok ? 'Último correcto: '.$ok->created_at->format('d/m H:i') : 'Ningún respaldo correcto');
    }

    private function servidor(CarbonImmutable $desde, array &$rec): array
    {
        $s = EstadoSistema::latest('id')->first();
        $bloqueos = count(array_filter($this->fail2ban->historial(1), fn ($e) => $e['tipo'] === 'Ban'));
        $rechazos = $this->registroCtl->rechazosDesde($desde);

        foreach ($s?->alertas ?? [] as $a) {
            $this->recomendar($rec, $a['nivel'], 'Servidor', $a['mensaje']);
        }

        return [
            'semaforo' => $s?->semaforo ?? Semaforo::GRIS,
            'datos' => [
                ['Disco /', $s?->porcentajeDisco() !== null ? $s->porcentajeDisco().' %' : '—', $s ? Formato::bytes($s->disco_usado, 1).' de '.Formato::bytes($s->disco_total, 1) : 'sin datos'],
                ['RAM', $s?->porcentajeRam() !== null ? $s->porcentajeRam().' %' : '—', $s ? Formato::bytes($s->ram_usada, 1).' de '.Formato::bytes($s->ram_total, 1) : 'sin datos'],
                ['Certificado', $s?->cert_dias !== null ? $s->cert_dias.' días' : '—', 'para vencer'],
                ['Fail2ban', $bloqueos.' '.($bloqueos === 1 ? 'bloqueo' : 'bloqueos'), 'en 24 h'],
                ['consola-ctl', $rechazos.' '.($rechazos === 1 ? 'rechazo' : 'rechazos'), 'en 24 h'],
                ['Servicios base', collect($s?->servicios ?? [])->every(fn ($e) => $e === 'active') && $s ? 'Todos activos' : 'Revisar', implode(', ', array_keys($s?->servicios ?? []))],
            ],
        ];
    }
}
