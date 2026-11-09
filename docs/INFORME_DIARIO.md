# Informe diario y avisos por correo (Paso 12)

## Qué envía la consola

| Correo | Cuándo | A quién |
|---|---|---|
| **Informe diario** | 07:00 (timer `consola-informe.timer`) | Destinatarios activos de **Configuración → Avisar**, según su nivel: todos los días, solo con amarillo o rojo, o solo con rojo |
| **Aviso «EN FALLA»** | Cuando una app (no de pruebas) o el servidor lleva 2 minutos en rojo | Destinatarios con «Avisos inmediatos» |
| **Aviso «Recuperada»** | Cuando vuelve a funcionar (solo si antes se avisó la falla) | Los mismos |

El informe trae, por app: operación de 24 h, ciberseguridad (`composer audit`), mantenimiento (versión de
Laravel y `composer outdated`) y respaldo; además el servidor y recomendaciones por reglas (sin IA).
Se guarda en **Monitoreo → Informes**.

## 1. Configurar el correo (una vez)

1. En la cuenta de Google Workspace que enviará (ej. `consola@gbpo.com.mx`): activar la verificación en
   2 pasos y crear una **contraseña de aplicación** (Cuenta de Google → Seguridad → Contraseñas de aplicación).
2. Como root, editar `/var/www/html/consola-central/.env`:

   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_SCHEME=smtp
   MAIL_USERNAME=consola@gbpo.com.mx
   MAIL_PASSWORD="<contraseña de aplicación, 16 letras>"
   MAIL_FROM_ADDRESS=consola@gbpo.com.mx
   MAIL_FROM_NAME="Consola Central"
   ```

   `MAIL_SCHEME=smtp` con el puerto 587 usa cifrado STARTTLS. No pongas `tls`: no es un valor válido.
3. `consola-desplegar --sin-pull` (o `runuser -u consola -- php artisan optimize:clear`).
4. En la consola: **Configuración → Avisar** → agregar destinatarios, y **Monitoreo → Informes →
   Enviar informe de prueba**.

Mientras `MAIL_MAILER=log`, nada sale del servidor: los correos quedan en `storage/logs/laravel.log`.

## 2. Carpeta para composer (una vez, como root)

`composer audit` corre como el usuario `consola` y necesita una carpeta propia:

```bash
install -d -o consola -g consola -m 0750 /var/lib/consola /var/lib/consola/.composer
```

## 3. Instalar el timer de las 07:00 (como root)

```bash
cp /var/www/html/consola-central/docs/systemd/consola-informe.* /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now consola-informe.timer
```

Primera corrida manual, para ver el resultado:

```bash
systemctl start consola-informe.service
journalctl -u consola-informe.service -n 20 --no-pager
```

## Si algo no sale

- **«Sin datos de vulnerabilidades»**: `consola` no pudo correr composer en la carpeta de la app. Probar:
  `runuser -u consola -- env COMPOSER_HOME=/var/lib/consola/.composer composer audit --locked --no-plugins -d /var/www/html/<app>`
- **Error de envío**: aparece en Monitoreo → Informes (columna «Enviado a») y en `storage/logs/laravel.log`.
  Lo más común es la contraseña de aplicación mal copiada o la cuenta sin verificación en 2 pasos.
- **Demasiados avisos inmediatos**: subir `minutos_confirmacion` en `config/consola.php` (sección `correo`).
