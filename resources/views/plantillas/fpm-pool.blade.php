; /etc/php/8.2/fpm/pool.d/{!! $pool !!}.conf
; Generado por Consola Central (plantilla igual a intranet.conf) — revisar antes de instalar.
[{!! $pool !!}]
user = {!! $usuario !!}
group = {!! $usuario !!}
listen = /run/php/php8.2-fpm-{!! $pool !!}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
pm.max_requests = 500
; Lo consulta la consola en /fpm-status (solo 127.0.0.1)
pm.status_path = /fpm-status
request_terminate_timeout = 300
request_slowlog_timeout = 10s
slowlog = /var/log/php8.2-fpm-{!! $pool !!}-slow.log
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 60M
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php8.2-fpm-{!! $pool !!}.log
