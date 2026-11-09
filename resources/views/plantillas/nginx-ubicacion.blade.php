@if ($tipo === 'octane')
    location / {
        proxy_pass http://127.0.0.1:{!! $puerto_interno !!};
        include snippets/laravel-proxy.conf;
    }
@else
    root {!! $ruta !!}/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm-{!! $pool !!}.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
@endif
