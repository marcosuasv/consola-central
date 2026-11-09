-- Base de datos y usuario aislado de {!! $nombre !!}.
-- La contraseña la genera el script al ejecutarse (no se guarda en la consola).
CREATE DATABASE IF NOT EXISTS `{!! $base_datos !!}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '{!! $usuario_bd !!}'@'{!! $host_bd !!}' IDENTIFIED BY '__CONTRASENA__';
GRANT ALL PRIVILEGES ON `{!! $base_datos !!}`.* TO '{!! $usuario_bd !!}'@'{!! $host_bd !!}';
FLUSH PRIVILEGES;
