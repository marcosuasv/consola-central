@if ($tipo === 'octane')
    location / {
        proxy_pass http://127.0.0.1:{!! $puerto_interno !!};
        include snippets/laravel-proxy.conf;
    }
@else
    root {!! $ruta !!}/public;
    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Estado del pool (lo usará la consola): solo desde el propio servidor
    location = /fpm-status {
        allow 127.0.0.1; deny all;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm-{!! $pool !!}.sock;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm-{!! $pool !!}.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_param HTTP_HOST $http_host;
        fastcgi_param SERVER_PORT $server_port;
        fastcgi_param HTTPS on;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* { deny all; }
@endif
