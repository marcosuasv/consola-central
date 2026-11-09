# Agregar una aplicación al servidor DebWebServ

La forma recomendada es el asistente de la consola: **Configuración → Agregar aplicación**.
Genera un script con todo lo de abajo para que root lo revise y lo ejecute. Esta guía describe
el mismo procedimiento a mano, por si hay que hacerlo sin la consola o revisar qué hace el script.

> Las plantillas que usa el asistente están en `resources/views/plantillas/`. Si las del servidor
> cambian, actualiza esas plantillas para que el asistente siga generando lo mismo.

## 0. Antes de empezar

| Dato | Ejemplo | Regla |
|---|---|---|
| Carpeta | `inventario` | Letras, números, `-` y `_`. Debe existir `/var/www/html/<carpeta>/artisan`. |
| Clave de servicio | `inventario` | Minúsculas, números y `-`. Las unidades se llaman `app-<clave>`. |
| Tipo | Octane o PHP-FPM | |
| Puerto interno | `18100` | Solo Octane. Rango 18xxx, libre. Escucha en 127.0.0.1. |
| Puertos públicos | `8100` | HTTPS. No repetir los de otras apps. |
| Base de datos / usuario | `inventario` | Usuario aislado: solo tiene permisos sobre su base. |

Puertos ya usados: revisa **Configuración → Aplicaciones** (columnas Interno y Públicos).

## 1. Servicios (Octane)

Crear en `/etc/systemd/system/`:

- `app-<clave>.service` — `php artisan octane:start --host=127.0.0.1 --port=<interno>`, `Restart=always`,
  `ExecReload=php artisan octane:reload`.
- `app-<clave>-queue.service` — `php artisan queue:work`, con `PartOf=app-<clave>.service`.
- `app-<clave>-scheduler.service` (oneshot, `php artisan schedule:run`) y
  `app-<clave>-scheduler.timer` (`OnCalendar=*-*-* *:*:00`), ambos con `PartOf=app-<clave>.service`.

```bash
systemctl daemon-reload
systemctl enable --now app-<clave>.service app-<clave>-queue.service app-<clave>-scheduler.timer
```

Las apps de **pruebas** no se habilitan al arranque (solo `daemon-reload`); se encienden desde la consola.

## 1b. Pool de PHP-FPM (apps FPM)

`/etc/php/8.2/fpm/pool.d/<pool>.conf` con `listen = /run/php/php8.2-fpm-<pool>.sock`, luego:

```bash
php-fpm8.2 -t && systemctl reload php8.2-fpm
```

## 2. Nginx

`/etc/nginx/sites-available/app-<clave>`:

- `listen <puerto> ssl;` por cada puerto público, certificado `/etc/nginx/ssl/local-cert.pem`.
- `error_page 497 https://$host:$server_port$request_uri;` (redirige HTTP a HTTPS en el mismo puerto).
- Octane: `proxy_pass http://127.0.0.1:<interno>;` + `include snippets/laravel-proxy.conf;`.
- FPM: `root /var/www/html/<carpeta>/public;` + `fastcgi_pass unix:/run/php/php8.2-fpm-<pool>.sock;`.
- Logs: `access_log /var/log/nginx/app-<clave>.access.log;` y `error_log …/app-<clave>.error.log;`
  (la consola y Fail2ban leen estos nombres).

```bash
ln -s /etc/nginx/sites-available/app-<clave> /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## 3. Base de datos

```sql
CREATE DATABASE `<bd>` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '<usuario>'@'localhost' IDENTIFIED BY '<contraseña larga y aleatoria>';
GRANT ALL PRIVILEGES ON `<bd>`.* TO '<usuario>'@'localhost';
```

La contraseña va solo en `/var/www/html/<carpeta>/.env`. No se guarda en la consola ni en el repositorio.

## 4. Configurar y migrar la app

En `.env`: `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Luego, como el usuario de la app:
`php artisan migrate --force` y `php artisan optimize`.

## 5. Permisos de lectura para la consola (después de migrar)

```sql
GRANT SELECT (user_id, ip_address, user_agent, last_activity) ON `<bd>`.`sessions` TO 'consola_ro'@'localhost';
GRANT SELECT (id, name, email) ON `<bd>`.`users` TO 'consola_ro'@'localhost';
GRANT SELECT (id, queue, payload, attempts, reserved_at, available_at, created_at) ON `<bd>`.`jobs` TO 'consola_ro'@'localhost';
GRANT SELECT (id, uuid, connection, queue, payload, exception, failed_at) ON `<bd>`.`failed_jobs` TO 'consola_ro'@'localhost';
GRANT SELECT (id, name, total_jobs, pending_jobs, failed_jobs, created_at, finished_at, cancelled_at) ON `<bd>`.`job_batches` TO 'consola_ro'@'localhost';
FLUSH PRIVILEGES;
```

Si la app no usa sesiones en BD, omite la primera línea.

## 6. Firewall y Fail2ban

- App interna: `ufw allow from 10.0.0.0/8 to any port <puerto> proto tcp` (y lo mismo para 172.16.0.0/12 y 192.168.0.0/16).
- App publicada a internet: `ufw allow <puerto>/tcp`.
- `systemctl restart fail2ban` para que tome el log nuevo.

## 7. Registrar en la consola

**Configuración → Aplicaciones → Crear** (o el botón «Registrar en la consola» del asistente).
En un minuto el recolector la mostrará en el Tablero.

## 8. Dominio (opcional)

Ver `docs/AGREGAR_DOMINIO.md` o **Configuración → Agregar dominio**.
