# /etc/nginx/sites-available/dominio-{!! $dominio !!}
# {!! $dominio !!} → {!! $nombre !!}. Generado por Consola Central — revisar antes de instalar.
@if ($redirigir_http)
server {
    listen 80;
    listen [::]:80;
    server_name {!! $dominio !!};
@if ($tls === 'letsencrypt')

    # Validación de Let's Encrypt
    location ^~ /.well-known/acme-challenge/ {
        root {!! $webroot_acme !!};
    }
@endif

    location / {
        return 301 https://$host$request_uri;
    }
}

@endif
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name {!! $dominio !!};

    ssl_certificate     {!! $certificado !!};
    ssl_certificate_key {!! $certificado_llave !!};
    ssl_protocols TLSv1.2 TLSv1.3;
@if ($hsts)
    add_header Strict-Transport-Security "max-age=31536000" always;
@endif

    client_max_body_size 50m;

    # Mismo log que la app: así la consola cuenta estas visitas (Seguridad, Logs)
    access_log /var/log/nginx/app-{!! $clave !!}.access.log;
    error_log  /var/log/nginx/app-{!! $clave !!}.error.log;

    @include('plantillas.nginx-ubicacion')
}
