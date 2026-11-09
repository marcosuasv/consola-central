-- Permisos de solo lectura para la Consola Central (consola_ro) sobre {!! $base_datos !!}.
-- Ejecutar DESPUÉS de correr las migraciones de la app (las tablas deben existir).
-- Si una tabla no existe en esta app, comentar su línea.
@if ($sesiones_en_bd)
GRANT SELECT (user_id, ip_address, user_agent, last_activity) ON `{!! $base_datos !!}`.`sessions` TO 'consola_ro'@'{!! $host_bd !!}';
@endif
GRANT SELECT (id, name, email) ON `{!! $base_datos !!}`.`users` TO 'consola_ro'@'{!! $host_bd !!}';
GRANT SELECT (id, queue, payload, attempts, reserved_at, available_at, created_at) ON `{!! $base_datos !!}`.`jobs` TO 'consola_ro'@'{!! $host_bd !!}';
GRANT SELECT (id, uuid, connection, queue, payload, exception, failed_at) ON `{!! $base_datos !!}`.`failed_jobs` TO 'consola_ro'@'{!! $host_bd !!}';
GRANT SELECT (id, name, total_jobs, pending_jobs, failed_jobs, created_at, finished_at, cancelled_at) ON `{!! $base_datos !!}`.`job_batches` TO 'consola_ro'@'{!! $host_bd !!}';
FLUSH PRIVILEGES;
