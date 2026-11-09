# /etc/nginx/sites-available/app-{!! $clave !!}
# Generado por Consola Central — revisar antes de instalar.
# Puertos: {!! implode(', ', $puertos_publicos) !!} (HTTPS). Un acceso por HTTP a un puerto HTTPS se redirige (error_page 497).
server {
@foreach ($puertos_publicos as $puerto)
    listen {!! $puerto !!} ssl;
    listen [::]:{!! $puerto !!} ssl;
@endforeach
    server_name _;

    ssl_certificate     {!! $certificado !!};
    ssl_certificate_key {!! $certificado_llave !!};
    error_page 497 https://$host:$server_port$request_uri;

    client_max_body_size 50m;

    access_log /var/log/nginx/app-{!! $clave !!}.access.log;
    error_log  /var/log/nginx/app-{!! $clave !!}.error.log;

    @include('plantillas.nginx-ubicacion')
}
