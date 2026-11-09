# Respaldos diarios de bases de datos (Paso 14)

Cada noche la consola respalda la base de cada app con **«Respaldar cada noche»** activado en su ficha
(por defecto: todas menos las de pruebas) y también la base de la propia consola.

## Cómo funciona

```
consola-respaldos.timer (02:17)  →  php artisan consola:respaldar  (usuario consola)
   └─ por cada base:  sudo -n consola-ctl respaldo crear <base>    (root: mysqldump | gzip)
                      sudo -n consola-ctl respaldo purgar <base> <días>
   └─ guarda el resultado en la tabla `respaldos` y en la Auditoría
```

- La consola **no puede leer** las bases de las apps ni los archivos de respaldo: solo los lista.
- `mysqldump --single-transaction` hace una copia consistente sin bloquear las tablas InnoDB.
- Se verifica el archivo (`gzip -t`) y se registra su tamaño y SHA-256.
- Retención: se borran las copias de más de N días (configurable por app), **conservando siempre las 3 más recientes**.
  Solo se purga cuando el respaldo del día salió bien.
- Si una app tiene respaldo activo y su último respaldo correcto tiene más de 26 h, el Tablero la pone en **amarillo**.

## Instalación (una vez, como root)

1. **Agregar la acción a consola-ctl.** Sigue las instrucciones de `docs/consola-ctl/respaldos.sh`
   (funciones + rama `respaldo)` en el `case`). Comprueba:

   ```bash
   runuser -u consola -- sudo -n /usr/local/sbin/consola-ctl respaldo crear consola_central
   ```

   Debe responder `OK archivo=/var/backups/consola/consola_central/... bytes=... sha256=...`.

2. **Instalar el timer:**

   ```bash
   cp /var/www/html/consola-central/docs/systemd/consola-respaldos.* /etc/systemd/system/
   systemctl daemon-reload
   systemctl enable --now consola-respaldos.timer
   systemctl list-timers consola-respaldos.timer
   ```

3. **Primera corrida manual** (para ver que todo sale bien):

   ```bash
   systemctl start consola-respaldos.service
   journalctl -u consola-respaldos.service -n 30 --no-pager
   ```

## Restaurar una base (manual, como root)

> Restaurar **reemplaza** los datos actuales. Hazlo primero en una base de prueba.

```bash
# Ver las copias disponibles
ls -lh /var/backups/consola/<base>/

# Restaurar en una base de PRUEBA para revisar
mysql -e "CREATE DATABASE prueba_restauracion"
gunzip -c /var/backups/consola/<base>/<archivo>.sql.gz \
  | sed 's/^CREATE DATABASE .*//; s/^USE `<base>`;/USE `prueba_restauracion`;/' | mysql

# Restaurar sobre la base real (detén antes la app desde la consola)
gunzip -c /var/backups/consola/<base>/<archivo>.sql.gz | mysql
```

El volcado incluye `CREATE DATABASE IF NOT EXISTS` y `USE`, por eso la restauración directa va a la misma base.

## Copia fuera del servidor (recomendado)

Los respaldos están en el mismo disco que las bases: si el disco falla, se pierden ambos.
Copia `/var/backups/consola/` a otro equipo o recurso de red (por ejemplo con `rsync` desde otro servidor,
o a un recurso compartido distinto de `/mnt/facturas_pdf`). La consola muestra en **Respaldos** el espacio que ocupan.
