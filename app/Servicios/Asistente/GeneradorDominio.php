<?php

namespace App\Servicios\Asistente;

use App\Models\Aplicacion;

/**
 * Genera (NO ejecuta) lo necesario para publicar una app con un dominio, ej. task.gbpo.com.mx:
 * sitio Nginx con TLS, certificado (Let's Encrypt, archivo propio o el local), firewall y pasos de DNS.
 */
class GeneradorDominio
{
    public const TLS_LETSENCRYPT = 'letsencrypt';

    public const TLS_ARCHIVO = 'archivo';

    public const TLS_LOCAL = 'local';

    public const OPCIONES_TLS = [
        self::TLS_LETSENCRYPT => "Let's Encrypt (gratis; el dominio debe apuntar a este servidor desde internet y el puerto 80 debe estar abierto)",
        self::TLS_ARCHIVO => 'Certificado propio (ej. comodín *.gbpo.com.mx comprado o de la red interna)',
        self::TLS_LOCAL => 'Certificado local autofirmado del servidor (el navegador mostrará advertencia)',
    ];

    public function normalizar(array $entrada): array
    {
        $app = Aplicacion::find((int) ($entrada['aplicacion_id'] ?? 0));
        Validador::exigir($app !== null, 'Elige una aplicación.');
        Validador::exigir($app->esFpm() || $app->puerto_interno, 'La app no tiene puerto interno configurado.');

        $dominio = strtolower(trim((string) ($entrada['dominio'] ?? '')));
        Validador::texto($dominio, Aplicacion::REGEX_DOMINIO, 'Dominio');
        $usadoPor = Aplicacion::whereKeyNot($app->id)->get()->first(fn (Aplicacion $a) => in_array($dominio, $a->listaDominios(), true));
        Validador::exigir($usadoPor === null, "El dominio ya está asignado a {$usadoPor?->nombre}.");

        $tls = array_key_exists($entrada['tls'] ?? '', self::OPCIONES_TLS) ? $entrada['tls'] : self::TLS_LETSENCRYPT;
        $c = config('consola.asistente');

        [$certificado, $llave] = match ($tls) {
            self::TLS_LETSENCRYPT => ["/etc/letsencrypt/live/{$dominio}/fullchain.pem", "/etc/letsencrypt/live/{$dominio}/privkey.pem"],
            self::TLS_ARCHIVO => [
                Validador::rutaEtc($entrada['certificado'] ?? '', 'Certificado'),
                Validador::rutaEtc($entrada['certificado_llave'] ?? '', 'Llave privada'),
            ],
            default => [$c['certificado'], $c['certificado_llave']],
        };

        $correo = trim((string) ($entrada['correo'] ?? ''));
        if ($tls === self::TLS_LETSENCRYPT) {
            Validador::texto($correo, Validador::REGEX_CORREO, 'Correo para Let\'s Encrypt');
        }

        return [
            'app' => $app,
            'nombre' => $app->nombre,
            'clave' => Validador::texto($app->clave(), Validador::REGEX_CLAVE, 'Clave de servicio'),
            'tipo' => $app->tipo,
            'ruta' => $app->ruta(),
            'puerto_interno' => $app->puerto_interno,
            'pool' => $app->pool_fpm ? Validador::texto($app->pool_fpm, Aplicacion::REGEX_POOL, 'Pool FPM') : null,
            'dominio' => $dominio,
            'tls' => $tls,
            'certificado' => $certificado,
            'certificado_llave' => $llave,
            'correo' => $correo,
            'publico' => (bool) ($entrada['publico'] ?? false),
            // apps-https ya redirige el puerto 80 para cualquier nombre; el bloque propio solo hace falta con Let's Encrypt
            'redirigir_http' => $tls === self::TLS_LETSENCRYPT || (bool) ($entrada['redirigir_http'] ?? false),
            'hsts' => (bool) ($entrada['hsts'] ?? false) && $tls !== self::TLS_LOCAL,
            'webroot_acme' => $c['webroot_acme'],
            'ip_servidor' => $c['ip_servidor'],
        ];
    }

    public function generar(array $entrada): Paquete
    {
        $d = $this->normalizar($entrada);
        $sitio = "/etc/nginx/sites-available/dominio-{$d['dominio']}";
        $habilitado = "/etc/nginx/sites-enabled/dominio-{$d['dominio']}";
        $conf = view('plantillas.nginx-dominio', $d)->render();

        $s = new ConstructorScript(
            "Dominio {$d['dominio']} → {$d['nombre']}",
            'Certificado: '.match ($d['tls']) {
                self::TLS_LETSENCRYPT => "Let's Encrypt",
                self::TLS_ARCHIVO => 'archivo propio',
                default => 'local autofirmado',
            }.'. Acceso: '.($d['publico'] ? 'desde internet' : 'solo red interna').'.',
        );

        $s->paso('Verificaciones')
            ->comando('command -v nginx >/dev/null || { echo "Nginx no está instalado." >&2; exit 1; }')
            ->comando('getent hosts '.escapeshellarg($d['dominio']).' || echo "  · AVISO: '.$d['dominio'].' todavía no resuelve en este servidor (revisa el DNS)."');

        if ($d['tls'] === self::TLS_LETSENCRYPT) {
            $s->paso("Obtener el certificado de Let's Encrypt")
                ->comando('command -v certbot >/dev/null || { echo "Instala certbot primero: apt install certbot" >&2; exit 1; }')
                ->comando('mkdir -p '.escapeshellarg($d['webroot_acme']))
                ->comentario('Sitio temporal solo HTTP para la validación (se reemplaza en el siguiente paso)')
                ->archivo($sitio, $this->soloHttp($d))
                ->comando('ln -sfn '.escapeshellarg($sitio).' '.escapeshellarg($habilitado))
                ->comando('nginx -t && systemctl reload nginx')
                ->comando('certbot certonly --webroot -w '.escapeshellarg($d['webroot_acme']).' -d '.escapeshellarg($d['dominio'])
                    .' --email '.escapeshellarg($d['correo']).' --agree-tos --non-interactive --deploy-hook "systemctl reload nginx"');
        } else {
            $s->paso('Verificar el certificado')
                ->comando('[[ -r '.escapeshellarg($d['certificado']).' && -r '.escapeshellarg($d['certificado_llave']).' ]] || { echo "No se encuentra el certificado o la llave." >&2; exit 1; }')
                ->comando('openssl x509 -in '.escapeshellarg($d['certificado']).' -noout -subject -enddate');
        }

        $s->paso('Sitio de Nginx con HTTPS')
            ->archivo($sitio, $conf)
            ->comando('ln -sfn '.escapeshellarg($sitio).' '.escapeshellarg($habilitado))
            ->comando('nginx -t')
            ->comando('systemctl reload nginx');

        $puertos = $d['redirigir_http'] ? [443, 80] : [443];
        $s->paso('Firewall (UFW)');
        foreach (GeneradorAplicacion::reglasUfw($puertos, $d['publico']) as $regla) {
            $s->comando($regla);
        }

        $s->paso('Prueba')
            ->comando('curl -sk -o /dev/null -w "  · https://'.$d['dominio'].' respondió HTTP %{http_code}\n" --resolve '.escapeshellarg($d['dominio'].':443:127.0.0.1').' '.escapeshellarg('https://'.$d['dominio'].'/').' || true');

        $pasos = [
            $d['publico']
                ? "DNS público: registro A {$d['dominio']} → IP pública que llega a este servidor (y reenviar 443".($d['redirigir_http'] ? '/80' : '').' en el firewall perimetral).'
                : "DNS interno: registro A {$d['dominio']} → {$d['ip_servidor']}.",
            "En {$d['ruta']}/.env cambia APP_URL=https://{$d['dominio']} y ejecuta php artisan config:cache como el usuario de la app.",
            $d['tipo'] === Aplicacion::TIPO_OCTANE ? "Recarga la app desde la Consola Central (botón Recargar de {$d['nombre']})." : 'Recarga PHP-FPM desde la Consola Central.',
        ];
        if ($d['tls'] === self::TLS_LETSENCRYPT) {
            $pasos[] = 'La renovación es automática (timer de certbot). Revisa: systemctl list-timers | grep certbot';
        }

        return new Paquete(
            "Dominio {$d['dominio']}",
            "dominio-{$d['dominio']}.sh",
            $s->final($pasos),
            [['ruta' => $sitio, 'titulo' => "Sitio Nginx {$d['dominio']}", 'contenido' => $conf]],
            $pasos,
        );
    }

    /** Agrega el dominio a la ficha de la app (y lo usa como URL de acceso). */
    public function guardarEnFicha(array $entrada): Aplicacion
    {
        $d = $this->normalizar($entrada);
        $app = $d['app'];
        $dominios = array_values(array_unique([...$app->listaDominios(), $d['dominio']]));

        $app->update([
            'dominios' => implode(',', $dominios),
            'url' => "https://{$d['dominio']}/",
        ]);

        return $app;
    }

    private function soloHttp(array $d): string
    {
        return <<<NGINX
        # Temporal: solo para validar {$d['dominio']} con Let's Encrypt
        server {
            listen 80;
            server_name {$d['dominio']};

            location ^~ /.well-known/acme-challenge/ {
                root {$d['webroot_acme']};
            }

            location / {
                return 404;
            }
        }
        NGINX;
    }
}
