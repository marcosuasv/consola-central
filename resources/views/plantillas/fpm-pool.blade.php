; /etc/php/8.2/fpm/pool.d/{!! $pool !!}.conf
; Generado por Consola Central — revisar antes de instalar.
[{!! $pool !!}]
user = {!! $usuario !!}
group = {!! $usuario !!}
listen = /run/php/php8.2-fpm-{!! $pool !!}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
php_admin_value[open_basedir] = {!! $ruta !!}/:/tmp/
php_admin_flag[expose_php] = off
