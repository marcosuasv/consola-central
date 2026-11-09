# Consola Central — guía para agentes de código (Codex / Claude Code)

> Lee este archivo completo antes de escribir código. Describe el servidor real donde corre
> la consola, las reglas de seguridad que NO se pueden romper y lo que falta construir.
> Idioma de la interfaz, comentarios y mensajes de commit: **español**.

---

## 1. Qué es

Panel web interno para **monitorear y controlar** las aplicaciones Laravel de un servidor
Debian 12 (`DebWebServ`, 10.2.0.170). Solo se usa **desde el propio servidor**
(`http://127.0.0.1:4900`, abierto por escritorio remoto). No se publica en la red ni en internet.

Funciones: tablero con semáforo, control de servicios, logs, usuarios conectados, colas,
seguridad (Fail2ban), auditoría, informe diario por correo y asistente para agregar apps.
**Sin IA**: todo el análisis se hace con reglas locales.

## 2. Stack (ya instalado)

| Pieza | Versión / valor |
|---|---|
| Laravel | 12.71 |
| Filament | 4.16 — panel id `consola`, `->path('')` (la raíz `/` es el panel) |
| PHP | 8.2 (CLI y FPM) — extensiones intl, zip, gd, bcmath, mbstring, xml, curl, pdo_mysql |
| BD | MariaDB 10.11, base `consola_central`, usuario `consola_app` |
| Locale / TZ | `es` / `America/Mexico_City` |
| Entorno | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:4900` |

Convenciones de Filament 4 que ya usa el proyecto:
- Recursos en `app/Filament/Resources/<Plural>/` con subcarpetas `Pages/`, `Schemas/`, `Tables/`.
- Iconos con `Filament\Support\Icons\Heroicon`; acciones de tabla con `->recordActions()` / `->toolbarActions()`
  y clases de `Filament\Actions\*`; layout con `Filament\Schemas\Components\Section`.
- `App\Models\User` implementa `FilamentUser` (`canAccessPanel`). Hoy: solo correos `@gbpo.com.mx`.

## 3. Cómo corre en el servidor (NO cambiar sin avisar)

- PHP-FPM pool propio `consola`, socket `/run/php/php8.2-fpm-consola.sock`, **usuario del sistema `consola`**
  (sin login), miembro de los grupos `adm` y `systemd-journal`.
- Nginx escucha **solo** en `127.0.0.1:4900` (`/etc/nginx/sites-available/consola-central`).
- Código propiedad de `sistemas`; `storage/` y `bootstrap/cache/` propiedad de `consola` (2775).
- Los comandos de consola (scheduler, recolector) se ejecutan como `consola`.

### 3.1 Lo que el usuario `consola` PUEDE leer sin privilegios
- `journalctl -u <unidad>` (systemd)
- `/var/log/nginx/*.log` (grupo adm), `/var/log/fail2ban.log`, `/var/log/consola-ctl.log`
- `/var/www/html/<app>/storage/logs/*.log` y archivos de sesión de la intranet
- `systemctl is-active|show <unidad>` (sin sudo), `findmnt`, `df`, `/proc/*`
- `git -c safe.directory='*' -C /var/www/html/<app> status|log` (solo lectura)

### 3.2 Lo que NO puede (y está bien así)
- Leer los `.env` de las apps (contienen contraseñas). **Nunca** intentes leerlos.
- Escribir en `/etc`, en las apps, ni ejecutar nada como root salvo lo de la sección 4.

## 4. REGLA DE ORO: acciones privilegiadas SOLO con `consola-ctl`

Toda acción que necesite root se hace **exclusivamente** con:

```
sudo -n /usr/local/sbin/consola-ctl <acción> ...
```

Acciones permitidas hoy (el script valida y rechaza cualquier otra cosa):

```
servicio start|stop|restart|reload|status|enable|disable app-<nombre>[.service|.timer]
fpm reload
f2b status [regla]
f2b unban <regla> <ip>
cola reintentar <carpeta-app> <id|uuid|all>
cola reiniciar  <carpeta-app>
respaldo crear  <base>            # Paso 14 (bloque en docs/consola-ctl/respaldos.sh)
respaldo purgar <base> <dias>     # Paso 14
```

Reglas de implementación:
- Usar **siempre** `Symfony\Component\Process\Process` (o `Illuminate\Support\Facades\Process`) con
  **arreglo de argumentos**, nunca cadenas concatenadas, `shell_exec`, `exec` ni backticks.
- Validar en PHP antes de llamar (nombre de unidad `^app-[a-z0-9-]+(\.service|\.timer)?$`, IP, carpeta
  `^[A-Za-z0-9_-]+$`), aunque el script vuelva a validar.
- Timeout en cada proceso (ej. 60 s). Capturar salida y código de retorno.
- **Cada acción** (éxito o fallo) se registra en la tabla `auditorias` (sección 7.7).
- Si una función nueva necesita otra acción privilegiada, **no la inventes**: deja la llamada preparada,
  documenta en el PR la línea exacta que hay que agregar a `consola-ctl` y el administrador la instala.

## 5. Bases de datos

### 5.1 Conexión propia
`mysql` (default) → `consola_central` con `consola_app` (control total de su base).

### 5.2 Conexión de solo lectura a las apps
Variables en `.env`: `DB_RO_HOST`, `DB_RO_PORT`, `DB_RO_USERNAME=consola_ro`, `DB_RO_PASSWORD`.
Crear en `config/database.php` una conexión `lectura` (driver mysql) con esas variables y **sin base fija**;
consultar con nombre calificado: `` DB::connection('lectura')->table('`GamTask`.sessions') `` o cambiando la
base con `->setDatabaseName()` en una conexión clonada. `consola_ro` tiene SELECT **solo en estas columnas**:

| Tabla | Columnas permitidas |
|---|---|
| `sessions` | user_id, ip_address, user_agent, last_activity |
| `users` | id, name, email |
| `jobs` | id, queue, payload, attempts, reserved_at, available_at, created_at |
| `failed_jobs` | id, uuid, connection, queue, payload, exception, failed_at |
| `job_batches` | id, name, total_jobs, pending_jobs, failed_jobs, created_at, finished_at, cancelled_at |

Nunca hagas `SELECT *` sobre estas tablas (fallará por permisos). La intranet **no** tiene `sessions`
(usa sesiones en archivos).

## 6. Aplicaciones del servidor (se precargan con `AplicacionesSeeder`)

| Nombre | Carpeta | Tipo | Servicio / pool | Interno | Públicos | Base de datos | Pruebas | Internet |
|---|---|---|---|---|---|---|---|---|
| GamTask | GamTask | octane | app-gamtask | 18070 | 443,4870,8070 | GamTask | no | no |
| pca-gam | pca-gam | octane | app-pcagam | 18089 | 8089,4889 | pca-gam | no | **sí** |
| akoc-control | akoc-control | octane | app-akoc | 18095 | 8095,4995 | akoc-control | no | no |
| Intranet GBPO | intranet_GBPO | fpm | pool `intranet` | — | 8080 | intranet_GBPO | no | no |
| GamTask (pruebas) | GamTask-pruebas | octane | app-gamtask-pruebas | 18075 | 8075 | GamTask-pruebas | sí | no |
| pca-gam (pruebas) | pca-gam-pruebas | octane | app-pcagam-pruebas | 18088 | 8088 | pca-gam-pruebas | sí | no |

Cada app Octane tiene además `app-<x>-queue.service` y `app-<x>-scheduler.timer` (PartOf de la app).
URL de salud: `http://127.0.0.1:<interno>/` (Octane) o `https://127.0.0.1:8080/` (intranet, certificado
autofirmado → no verificar TLS). Respuesta 2xx/3xx = OK. Ojo: el antivirus del servidor corta peticiones
locales al puerto 443; usar siempre el puerto interno.
Las de pruebas **no** arrancan con el servidor; un estado "inactivo" en ellas es normal (gris, no rojo).

Otros recursos a vigilar: MariaDB (`mariadb.service`), Nginx, PHP-FPM (`php8.2-fpm`), Fail2ban,
montaje CIFS `/mnt/facturas_pdf` (lo usa pca-gam), disco `/`, certificado `/etc/nginx/ssl/local-cert.pem`.

## 7. Lo que hay que construir (por pasos; cada paso = un PR)

### Paso 5 — Registro de aplicaciones
Migración `aplicaciones` (nombre, carpeta única, tipo octane|fpm, servicio, servicio_cola,
timer_scheduler, pool_fpm, puerto_interno, puertos_publicos, url, url_salud, base_datos, usuario_bd,
sesiones_en_bd, log_nginx, es_pruebas, arranque_automatico, publica_internet, monitorear, orden, notas),
modelo `App\Models\Aplicacion` (`$table='aplicaciones'`), seeder `AplicacionesSeeder` con la tabla de la
sección 6, y recurso Filament **Aplicaciones** (lista + formulario por secciones, validaciones con regex).

### Paso 6 — Recolector y Tablero
- Comando `consola:recolectar` (cada minuto; el timer de systemd lo crea el administrador) que guarda en
  `estados_app` por app: activo del servicio/cola/timer (`systemctl show -p ActiveState,SubState,
  MainPID,MemoryCurrent,CPUUsageNSec,ActiveEnterTimestamp,NRestarts`), código HTTP y latencia de
  `url_salud`, sesiones activas (últimos 15 min), jobs pendientes y fallidos.
  Y en `estados_sistema`: carga, RAM, disco `/`, MariaDB/Nginx/FPM/Fail2ban activos, montaje de facturas,
  días para vencer el certificado. Purgar registros de más de 7 días.
- **Tablero** (página principal): una tarjeta por app con semáforo, uptime, RAM, reinicios, latencia,
  sesiones, pendientes/fallidos; widgets del sistema; refresco automático (`->poll('15s')` o Livewire `wire:poll`).
- Semáforo por app: **rojo** = servicio inactivo (si no es de pruebas) o salud ≠ 2xx/3xx o >5 fallidos 24 h;
  **amarillo** = latencia > 2 s, reinicios en 24 h, errores en log, pendientes > 50; **verde** = resto;
  **gris** = app de pruebas apagada o `monitorear=false`.

### Paso 7 — Control, roles y auditoría
- Botones por app: Encender, Apagar, Reiniciar, Recargar (Octane: `servicio reload app-x`;
  FPM: `fpm reload`), Reiniciar cola. Confirmación modal; Apagar/Reiniciar de apps **no** de pruebas pide
  escribir el nombre de la app.
- Usuarios: columnas `rol` (admin|lectura) y `activo`. `canAccessPanel` = activo y `@gbpo.com.mx`.
  Solo **admin** ve botones de control. MFA de Filament 4 (`AppAuthentication`) obligatorio para admin.
- Recurso de solo lectura **Auditoría**: tabla `auditorias` (user_id, accion, objetivo, parametros json,
  resultado ok|error, codigo_salida, salida (texto recortado 5 KB), created_at). Sin editar ni borrar.

### Paso 8 — Logs
Página por app con pestañas: Laravel (`storage/logs/laravel*.log`, últimas N líneas, filtro por nivel
ERROR/WARNING, búsqueda), systemd (`journalctl -u <servicio> -n N --no-pager -o short-iso`), Nginx acceso
y error. Leer solo el final del archivo (no cargar archivos completos; usar `tail` o `SplFileObject`).
Ocultar en pantalla posibles secretos (patrones `password=`, `token`, `APP_KEY`).

### Paso 9 — Usuarios conectados
Por app con `sesiones_en_bd`: `sessions` donde `last_activity` > ahora−15 min, unido a `users` (nombre,
correo), con IP, navegador simplificado y hace cuánto. Intranet: leer `storage/framework/sessions/*`
modificados en los últimos 15 min y extraer el id de `login_web_...";i:<id>` (solo contar si no hay id).
Total de usuarios conectados en el Tablero.

### Paso 10 — Colas
Por app: pendientes y fallidos (nombre del job desde `payload->displayName`, intentos, fecha, primera línea
de la excepción). Botones: Reintentar uno / todos (`cola reintentar`), Reiniciar workers (`cola reiniciar`).

### Paso 11 — Seguridad
- Fail2ban: reglas, IPs bloqueadas por regla (`f2b status <regla>`), botón Desbloquear (`f2b unban`).
- Histórico de bloqueos desde `/var/log/fail2ban.log` (últimos 7 días).
- Visitas desde IPs públicas (no 10/8, 172.16/12, 192.168/16, 127/8) por app desde los logs de Nginx,
  con conteo de 4xx/5xx; destacar pca-gam (única pública).
- Registro de `consola-ctl.log` (acciones rechazadas = alerta).

### Paso 12 — Informe diario y correo
- Comando `consola:informe-diario` (07:00, timer del administrador). Por app calcula semáforo de
  **ciberseguridad y mantenimiento**:
  - `composer audit --format=json --locked` en la carpeta de la app (como `consola`,
    `COMPOSER_HOME=/var/lib/consola/.composer`) → vulnerabilidades (rojo si hay altas/críticas).
  - `composer outdated --direct --format=json` → librerías desactualizadas (amarillo si hay major).
  - Versión de Laravel (de `vendor/laravel/framework/src/Illuminate/Foundation/Application.php`) vs
    soporte oficial (Laravel 8: sin soporte → rojo).
  - Errores en `laravel.log` últimas 24 h, reinicios, caídas, jobs fallidos, bloqueos de Fail2ban,
    visitas públicas sospechosas, días para vencer el certificado, espacio en disco.
  - `git status` sin commitear (aviso informativo; en akoc-control es esperado, ver notas de la app).
  - Recomendaciones en texto según reglas (ej. "actualizar paquete X a Y", "migrar Laravel 8").
- Guardar el informe en tabla `informes` (json + html) y mostrarlo en la consola (historial).
- Recurso **Avisar** (destinatarios: nombre, correo, activo, nivel mínimo: todo|amarillo|rojo).
- Enviar por SMTP de Google Workspace (`MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`,
  `MAIL_SCHEME`/`MAIL_ENCRYPTION` tls, contraseña de aplicación en `.env` — la pone el administrador).
  Botón "Enviar informe de prueba". El correo es HTML simple con tabla de semáforos.

### Paso 13 — Asistente y guía para agregar aplicaciones
- Asistente en Filament que, a partir de los datos de una app nueva (carpeta, tipo, puertos, base de datos),
  **genera** (no ejecuta) los archivos con las plantillas usadas en este servidor: units systemd
  (app, queue, scheduler service+timer), sitio Nginx (con `error_page 497`, `snippets/laravel-proxy.conf`,
  log `app-<x>.access.log`), pool FPM si aplica, SQL del usuario de BD aislado y de los GRANT de
  `consola_ro`, regla UFW (solo redes privadas salvo que sea pública) y `systemctl restart fail2ban`.
  Entrega un script bash para que root lo revise y ejecute, y crea la ficha en `aplicaciones`.
- `docs/AGREGAR_APLICACION.md` con el procedimiento manual paso a paso.

### Paso 14 — Respaldos diarios de bases de datos
- Comando `consola:respaldar` (timer `consola-respaldos.timer`, 02:17) que respalda la base de cada app con
  `respaldo_activo` (por defecto todas menos pruebas) y la de la consola, vía `consola-ctl respaldo crear`
  (root: `mysqldump --single-transaction | gzip` en `/var/backups/consola/<base>/`, carpetas root:consola 0750,
  archivos 0640). Purga por días conservando siempre las 3 copias más recientes.
- Tabla `respaldos` (historial), página **Respaldos** con «Respaldar ahora» (admin + 2FA) y alerta amarilla en
  el Tablero si el último respaldo correcto tiene más de 26 h. Guía: `docs/RESPALDOS.md`.

## 8. Reglas generales de código
- Nada de secretos en el repositorio. `.env` está en `.gitignore`.
- Migraciones solo aditivas; nunca modificar una migración ya ejecutada.
- Todo texto visible en español. Fechas en `America/Mexico_City`.
- Las consultas a otras apps: solo vía conexión `lectura`, solo columnas permitidas, con `try/catch`
  (si una app no tiene la tabla, mostrar "sin datos", no romper la página).
- Lecturas de archivos/procesos con límites (líneas, bytes, timeout).
- Cada PR: describir qué cambia, migraciones nuevas, comandos de despliegue y si requiere cambios en el
  servidor (consola-ctl, timers, .env).

## 9. Despliegue en el servidor (lo hace el administrador)

Un solo comando, como root (funciona con `su` o `su -`):

```bash
consola-desplegar            # git pull + composer + migraciones + cachés + permisos + reload + comprobación
consola-desplegar --sin-pull # despliega el código que ya está en la carpeta
```

Se instala una vez copiándolo fuera del repositorio (así solo root puede cambiarlo); repetir si `bin/consola-desplegar` cambia:

```bash
install -m 0755 -o root -g root /var/www/html/consola-central/bin/consola-desplegar /usr/local/sbin/consola-desplegar
```

No ejecuta el seeder ni toca el `.env`. Pasos equivalentes a mano:

```bash
# como sistemas
cd /var/www/html/consola-central && git pull
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
# como root (su -)
cd /var/www/html/consola-central
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
runuser -u consola -- php artisan package:discover
runuser -u consola -- php artisan migrate --force
runuser -u consola -- php artisan db:seed --class=AplicacionesSeeder --force   # solo si cambió; sobrescribe las fichas
runuser -u consola -- php artisan optimize:clear
runuser -u consola -- php artisan filament:optimize-clear
chown -R consola:consola storage bootstrap/cache
systemctl reload php8.2-fpm
```
