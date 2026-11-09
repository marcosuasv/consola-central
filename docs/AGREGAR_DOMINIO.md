# Agregar un dominio a una aplicación (ej. task.gbpo.com.mx → GamTask)

Recomendado: **Configuración → Agregar dominio** en la consola (o «Más → Agregar dominio» en el detalle
de la app). Genera el script; root lo revisa y lo ejecuta. Esta guía explica qué hace.

## 1. DNS (antes o después del script)

| Acceso | Registro |
|---|---|
| Solo red interna | En el DNS interno: `A task.gbpo.com.mx → 10.2.0.170` |
| Desde internet | En el DNS público de gbpo.com.mx: `A task.gbpo.com.mx → <IP pública>` y reenviar 443 (y 80) del firewall perimetral a 10.2.0.170 |

Comprobar: `getent hosts task.gbpo.com.mx`.

## 2. Certificado

| Opción | Cuándo | Archivos |
|---|---|---|
| Let's Encrypt | El dominio resuelve desde internet y el puerto 80 llega al servidor. Gratis, renovación automática. | `/etc/letsencrypt/live/<dominio>/fullchain.pem` y `privkey.pem` |
| Certificado propio | Comodín `*.gbpo.com.mx` comprado o de una CA interna. | Rutas en `/etc` que indiques |
| Local autofirmado | Pruebas o red interna sin certificado. El navegador muestra advertencia. | `/etc/nginx/ssl/local-cert.pem` |

Let's Encrypt (lo hace el script):

```bash
apt install certbot            # si no está instalado
mkdir -p /var/www/letsencrypt
# sitio temporal en el puerto 80 que sirve /.well-known/acme-challenge/ desde /var/www/letsencrypt
certbot certonly --webroot -w /var/www/letsencrypt -d task.gbpo.com.mx --email <correo> --agree-tos --non-interactive \
  --deploy-hook "systemctl reload nginx"
```

## 3. Sitio de Nginx

`/etc/nginx/sites-available/dominio-task.gbpo.com.mx`:

- Bloque en el puerto 80 que redirige a HTTPS (y sirve la validación de Let's Encrypt).
- Bloque `listen 443 ssl;` con `server_name task.gbpo.com.mx;` que hace `proxy_pass` al puerto interno
  de la app (o `fastcgi_pass` al pool si es FPM).
- **Usa el mismo log de la app** (`/var/log/nginx/app-<clave>.access.log`) para que la consola y Fail2ban
  vean esas visitas.

Convive con el sitio actual de la app: Nginx elige el bloque por el nombre (SNI), así que
`https://task.gbpo.com.mx` y `https://10.2.0.170:8070` funcionan a la vez.

```bash
ln -s /etc/nginx/sites-available/dominio-task.gbpo.com.mx /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## 4. Firewall

- Solo red interna: permitir 443 (y 80) desde 10.0.0.0/8, 172.16.0.0/12 y 192.168.0.0/16.
- Desde internet: `ufw allow 443/tcp` (y `80/tcp` si se redirige o se usa Let's Encrypt).

## 5. La app

En `/var/www/html/<carpeta>/.env`: `APP_URL=https://task.gbpo.com.mx`, luego `php artisan config:cache`
como el usuario de la app y **Recargar** la app desde la consola.

## 6. Registrar en la consola

Botón «Guardar dominio en la ficha» del asistente (o editar la app y llenar «Dominios»).
El dominio queda como URL de acceso de la app.
