<?php

/*
 * Parámetros de monitoreo de la Consola Central.
 * Todo lo que aquí se lee del servidor es de solo lectura (ver AGENTS.md, sección 3.1).
 */
return [

    // Los administradores necesitan verificación en dos pasos para usar los controles.
    'mfa_admin' => env('CONSOLA_MFA_ADMIN', true),

    // Ventana para considerar una sesión como "conectada".
    'minutos_sesion' => 15,

    // Días que se conservan los registros del recolector.
    'dias_historial' => 7,

    // Umbrales del semáforo por aplicación.
    'umbrales' => [
        'latencia_ms' => 2000,     // amarillo si se supera
        'fallidos_24h' => 5,       // rojo si se supera
        'pendientes' => 50,        // amarillo si se supera
    ],

    // Umbrales del sistema.
    'sistema' => [
        'disco_amarillo' => 80,    // % usado
        'disco_rojo' => 90,
        'ram_amarillo' => 85,
        'ram_rojo' => 95,
        'cert_dias_amarillo' => 30,
        'cert_dias_rojo' => 7,
    ],

    // Servicios del sistema que se vigilan (nombre visible => unidad).
    'servicios_sistema' => [
        'MariaDB' => 'mariadb.service',
        'Nginx' => 'nginx.service',
        'PHP-FPM' => 'php8.2-fpm.service',
        'Fail2ban' => 'fail2ban.service',
    ],

    'servicio_fpm' => 'php8.2-fpm.service',

    'montaje_facturas' => '/mnt/facturas_pdf',

    'certificado' => '/etc/nginx/ssl/local-cert.pem',

    // Límites de lectura de archivos y procesos.
    'limites' => [
        'timeout_proceso' => 10,         // segundos
        'timeout_http' => 5,             // segundos
        'bytes_log' => 1024 * 1024,      // máximo que se lee del final de un log
        'lineas_log' => 500,             // máximo de líneas en pantalla
        'archivos_sesion' => 3000,       // máximo de archivos de sesión a revisar (intranet)
        'bytes_seguridad' => 8 * 1024 * 1024, // máximo que se lee de fail2ban.log y de cada access.log
    ],

    // Valores por defecto del asistente para agregar apps y dominios (Paso 13).
    // Ajustarlos a las plantillas reales del servidor si difieren.
    'asistente' => [
        'ip_servidor' => '10.2.0.170',
        'php' => '/usr/bin/php',
        'usuario_apps' => 'www-data',
        'octane_servidor' => 'roadrunner',
        'certificado' => '/etc/nginx/ssl/local-cert.pem',
        'certificado_llave' => '/etc/nginx/ssl/local-key.pem',
        'host_bd' => 'localhost',
        'redes_privadas' => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
        'webroot_acme' => '/var/www/letsencrypt',
        'rango_puertos_internos' => [18000, 18999],
        // Puertos que el asistente nunca asigna a una app
        'puertos_reservados' => [22 => 'SSH', 80 => 'HTTP (redirección apps-https)', 3306 => 'MariaDB', 4900 => 'Consola Central'],
    ],

    // Soporte oficial de Laravel (fin de correcciones de seguridad).
    // Fuente: https://laravel.com/docs/releases#support-policy — revisar cada año.
    'soporte_laravel' => [
        8 => ['errores' => '2022-07-26', 'seguridad' => '2023-01-24'],
        9 => ['errores' => '2023-08-08', 'seguridad' => '2024-02-06'],
        10 => ['errores' => '2024-08-06', 'seguridad' => '2025-02-04'],
        11 => ['errores' => '2025-09-03', 'seguridad' => '2026-03-12'],
        12 => ['errores' => '2026-08-13', 'seguridad' => '2027-02-24'],
        13 => ['errores' => '2027-08-01', 'seguridad' => '2028-02-01'],
    ],
];
