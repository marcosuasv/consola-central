<?php

namespace App\Servicios\Asistente;

use App\Models\Aplicacion;

/**
 * Genera (NO ejecuta) la configuración de una app nueva: units systemd o pool FPM, sitio Nginx,
 * base de datos y usuario aislado, permisos de consola_ro, reglas UFW y reinicio de Fail2ban.
 */
class GeneradorAplicacion
{
    /**
     * @param  array<string, mixed>  $entrada  datos del formulario
     * @return array<string, mixed> datos validados y normalizados
     */
    public function normalizar(array $entrada): array
    {
        $c = config('consola.asistente');
        $carpeta = Validador::texto($entrada['carpeta'] ?? '', Aplicacion::REGEX_CARPETA, 'Carpeta');
        $tipo = in_array($entrada['tipo'] ?? '', [Aplicacion::TIPO_OCTANE, Aplicacion::TIPO_FPM], true) ? $entrada['tipo'] : Aplicacion::TIPO_OCTANE;
        $clave = Validador::texto($entrada['clave'] ?? '', Validador::REGEX_CLAVE, 'Clave de servicio');

        $d = [
            'nombre' => Validador::texto($entrada['nombre'] ?? '', Validador::REGEX_NOMBRE, 'Nombre'),
            'carpeta' => $carpeta,
            'ruta' => Aplicacion::RUTA_BASE.'/'.$carpeta,
            'tipo' => $tipo,
            'clave' => $clave,
            'usuario' => Validador::texto($entrada['usuario'] ?? $c['usuario_apps'], Validador::REGEX_USUARIO_SO, 'Usuario del sistema'),
            'php' => $c['php'],
            'octane_servidor' => in_array($entrada['octane_servidor'] ?? '', Validador::SERVIDORES_OCTANE, true) ? $entrada['octane_servidor'] : $c['octane_servidor'],
            'puerto_interno' => $tipo === Aplicacion::TIPO_OCTANE ? Validador::puerto($entrada['puerto_interno'] ?? null, 'Puerto interno', 1024) : null,
            'pool' => $tipo === Aplicacion::TIPO_FPM ? Validador::texto($entrada['pool'] ?? $clave, Aplicacion::REGEX_POOL, 'Pool FPM') : null,
            'puertos_publicos' => Validador::puertos($entrada['puertos_publicos'] ?? '', 'Puertos públicos'),
            'publica' => (bool) ($entrada['publica'] ?? false),
            'pruebas' => (bool) ($entrada['pruebas'] ?? false),
            'crear_bd' => (bool) ($entrada['crear_bd'] ?? true),
            'base_datos' => Validador::texto($entrada['base_datos'] ?? '', Aplicacion::REGEX_BD, 'Base de datos'),
            'usuario_bd' => Validador::texto($entrada['usuario_bd'] ?? '', Validador::REGEX_USUARIO_BD, 'Usuario de BD'),
            'sesiones_en_bd' => (bool) ($entrada['sesiones_en_bd'] ?? true),
            'host_bd' => $c['host_bd'],
            'certificado' => $c['certificado'],
            'certificado_llave' => $c['certificado_llave'],
        ];

        $ocupados = Validador::puertosOcupados();
        foreach (array_filter([$d['puerto_interno'], ...$d['puertos_publicos']]) as $p) {
            Validador::exigir(! isset($ocupados[$p]), "El puerto {$p} ya lo usa ".($ocupados[$p] ?? '').'.');
        }
        Validador::exigir(! in_array($d['puerto_interno'], $d['puertos_publicos'], true), 'El puerto interno no puede ser también público.');
        Validador::exigir(! Aplicacion::where('carpeta', $carpeta)->exists(), "Ya existe una app registrada con la carpeta {$carpeta}.");

        return $d;
    }

    public function generar(array $entrada): Paquete
    {
        $d = $this->normalizar($entrada);
        $archivos = [];
        $s = new ConstructorScript(
            "Nueva aplicación: {$d['nombre']} ({$d['carpeta']})",
            $d['tipo'] === Aplicacion::TIPO_OCTANE
                ? "Octane en 127.0.0.1:{$d['puerto_interno']}, puertos públicos ".implode(',', $d['puertos_publicos']).'.'
                : "PHP-FPM (pool {$d['pool']}), puertos públicos ".implode(',', $d['puertos_publicos']).'.',
        );

        $s->paso('Verificar la carpeta de la app')
            ->comando('[[ -f '.escapeshellarg($d['ruta'].'/artisan').' ]] || { echo "No existe '.$d['ruta'].'/artisan: copia o clona la app primero." >&2; exit 1; }')
            ->comando('id '.escapeshellarg($d['usuario']).' >/dev/null || { echo "No existe el usuario '.$d['usuario'].'" >&2; exit 1; }');

        if ($d['tipo'] === Aplicacion::TIPO_OCTANE) {
            $s->paso('Unidades de systemd');
            foreach ([
                "app-{$d['clave']}.service" => 'systemd-app',
                "app-{$d['clave']}-queue.service" => 'systemd-cola',
                "app-{$d['clave']}-scheduler.service" => 'systemd-scheduler-service',
                "app-{$d['clave']}-scheduler.timer" => 'systemd-scheduler-timer',
            ] as $unidad => $plantilla) {
                $archivos[] = $this->agregar($s, "/etc/systemd/system/{$unidad}", $unidad, $plantilla, $d);
            }
            $s->comando('systemctl daemon-reload');
        } else {
            $s->paso('Pool de PHP-FPM');
            $archivos[] = $this->agregar($s, "/etc/php/8.2/fpm/pool.d/{$d['pool']}.conf", "Pool FPM {$d['pool']}", 'fpm-pool', $d);
            $s->comando('php-fpm8.2 -t');
        }

        $s->paso('Sitio de Nginx');
        $sitio = "/etc/nginx/sites-available/app-{$d['clave']}";
        $archivos[] = $this->agregar($s, $sitio, "Sitio Nginx app-{$d['clave']}", 'nginx-app', $d);
        $s->comando('ln -sfn '.escapeshellarg($sitio).' '.escapeshellarg("/etc/nginx/sites-enabled/app-{$d['clave']}"))
            ->comando('nginx -t');

        $consolaRo = view('plantillas.sql-consola-ro', $d)->render();
        $archivos[] = ['ruta' => "/root/consola-ro-{$d['clave']}.sql", 'titulo' => 'Permisos de consola_ro (después de migrar)', 'contenido' => $consolaRo];

        if ($d['crear_bd']) {
            $sql = view('plantillas.sql-bd', $d)->render();
            $archivos[] = ['ruta' => '(se ejecuta con mysql)', 'titulo' => 'Base de datos y usuario', 'contenido' => $sql];
            $s->paso('Base de datos y usuario aislado')
                ->comando('CONTRASENA_BD=$(openssl rand -base64 48 | tr -dc "A-Za-z0-9" | head -c 32)')
                ->comando('SQL_TMP=$(mktemp)')
                // Heredoc entre comillas: los acentos graves del SQL no se interpretan
                ->comando("cat > \"\$SQL_TMP\" <<'SQL_CONSOLA'\n".rtrim($sql)."\nSQL_CONSOLA")
                // La contraseña solo tiene letras y números: segura para sed
                ->comando('sed -i "s/__CONTRASENA__/${CONTRASENA_BD}/" "$SQL_TMP"')
                ->comando('mysql < "$SQL_TMP"')
                ->comando('shred -u "$SQL_TMP" 2>/dev/null || rm -f "$SQL_TMP"');
        }

        $s->paso('Permisos de lectura para la consola (se aplican después de migrar)');
        $this->agregarSinPlantilla($s, "/root/consola-ro-{$d['clave']}.sql", $consolaRo, '0600');

        $s->paso('Firewall (UFW)');
        foreach ($this->reglasUfw($d['puertos_publicos'], $d['publica']) as $regla) {
            $s->comando($regla);
        }

        $s->paso('Activar servicios');
        if ($d['tipo'] === Aplicacion::TIPO_OCTANE) {
            $s->comando($d['pruebas']
                ? "echo '  · App de pruebas: no se habilita al arranque. Enciéndela desde la consola.'"
                : "systemctl enable --now app-{$d['clave']}.service app-{$d['clave']}-queue.service app-{$d['clave']}-scheduler.timer");
        } else {
            $s->comando('systemctl reload php8.2-fpm');
        }
        $s->comando('systemctl reload nginx')
            ->comando('systemctl restart fail2ban');

        $pasos = [];
        if ($d['crear_bd']) {
            $s->comando("echo\necho \"Contraseña de BD de {$d['usuario_bd']}: \${CONTRASENA_BD}\"\necho \"Ponla en {$d['ruta']}/.env (DB_DATABASE={$d['base_datos']}, DB_USERNAME={$d['usuario_bd']}, DB_PASSWORD=...) y NO la guardes en otro lado.\"");
        }
        $pasos[] = "Configura {$d['ruta']}/.env (APP_URL, DB_*), ejecuta las migraciones y php artisan optimize como {$d['usuario']}.";
        $pasos[] = "Después de migrar: mysql < /root/consola-ro-{$d['clave']}.sql (permisos de lectura para la consola).";
        if ($d['tipo'] === Aplicacion::TIPO_OCTANE && ! $d['pruebas']) {
            $pasos[] = "Reinicia la app desde la consola (o systemctl restart app-{$d['clave']}) tras configurar el .env.";
        }
        $pasos[] = 'Comprueba en la Consola Central que la app aparezca en verde en el Tablero.';

        return new Paquete(
            "Nueva aplicación {$d['nombre']}",
            "nueva-app-{$d['clave']}.sh",
            $s->final($pasos),
            $archivos,
            $pasos,
        );
    }

    /** Datos para crear la ficha en `aplicaciones`. */
    public function ficha(array $entrada): array
    {
        $d = $this->normalizar($entrada);
        $octane = $d['tipo'] === Aplicacion::TIPO_OCTANE;

        return [
            'nombre' => $d['nombre'],
            'carpeta' => $d['carpeta'],
            'tipo' => $d['tipo'],
            'servicio' => $octane ? "app-{$d['clave']}" : null,
            'servicio_cola' => $octane ? "app-{$d['clave']}-queue" : null,
            'timer_scheduler' => $octane ? "app-{$d['clave']}-scheduler.timer" : null,
            'pool_fpm' => $d['pool'],
            'puerto_interno' => $d['puerto_interno'],
            'puertos_publicos' => implode(',', $d['puertos_publicos']),
            'url' => 'https://'.config('consola.asistente.ip_servidor').':'.$d['puertos_publicos'][0].'/',
            'url_salud' => $octane ? "http://127.0.0.1:{$d['puerto_interno']}/" : 'https://127.0.0.1:'.$d['puertos_publicos'][0].'/',
            'base_datos' => $d['base_datos'],
            'usuario_bd' => $d['usuario_bd'],
            'sesiones_en_bd' => $d['sesiones_en_bd'],
            'log_nginx' => "/var/log/nginx/app-{$d['clave']}.access.log",
            'es_pruebas' => $d['pruebas'],
            'arranque_automatico' => ! $d['pruebas'],
            'publica_internet' => $d['publica'],
            'monitorear' => true,
            'orden' => (int) Aplicacion::max('orden') + 10,
            'notas' => 'Creada con el asistente el '.now()->format('d/m/Y').'.',
        ];
    }

    /** @return list<string> */
    public static function reglasUfw(array $puertos, bool $publica): array
    {
        $reglas = [];
        foreach ($puertos as $p) {
            if ($publica) {
                $reglas[] = "ufw allow {$p}/tcp comment 'consola: publico'";
            } else {
                foreach (config('consola.asistente.redes_privadas') as $red) {
                    $reglas[] = "ufw allow from {$red} to any port {$p} proto tcp comment 'consola: red interna'";
                }
            }
        }

        return $reglas;
    }

    private function agregar(ConstructorScript $s, string $ruta, string $titulo, string $plantilla, array $d): array
    {
        $contenido = view("plantillas.{$plantilla}", $d)->render();
        $s->archivo($ruta, $contenido);

        return ['ruta' => $ruta, 'titulo' => $titulo, 'contenido' => $contenido];
    }

    private function agregarSinPlantilla(ConstructorScript $s, string $ruta, string $contenido, string $modo): void
    {
        $s->archivo($ruta, $contenido, $modo);
    }
}
