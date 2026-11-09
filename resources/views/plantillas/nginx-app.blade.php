# /etc/nginx/sites-available/app-{!! $clave !!}
# {!! $nombre !!} {!! $pruebas ? 'PRUEBAS' : 'PRODUCCION' !!} -> {!! $tipo === 'octane' ? '127.0.0.1:'.$puerto_interno : 'PHP-FPM pool '.$pool !!}
# Generado por Consola Central (mismo formato que app-gamtask) — revisar antes de instalar.
@foreach ($puertos_publicos as $puerto)
server {
    listen {!! $puerto !!} ssl;
    server_name {!! $ip_servidor !!};
    ssl_certificate     {!! $certificado !!};
    ssl_certificate_key {!! $certificado_llave !!};
@if ($puerto !== 443)
    error_page 497 =301 https://$host:$server_port$request_uri;
@endif
    client_max_body_size 50M;
    access_log /var/log/nginx/app-{!! $clave !!}.access.log;
    error_log  /var/log/nginx/app-{!! $clave !!}.error.log;

    @include('plantillas.nginx-ubicacion')
}
@if (! $loop->last)

@endif
@endforeach
