# /etc/systemd/system/app-{!! $clave !!}-queue.service
# Sin [Install]: arranca por el Wants= de app-{!! $clave !!}.service y se detiene con ella (PartOf).
[Unit]
Description={!! $nombre !!} {!! $pruebas ? 'PRUEBAS' : 'PRODUCCION' !!} - worker de colas
After=app-{!! $clave !!}.service
PartOf=app-{!! $clave !!}.service

[Service]
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
ExecStart={!! $php !!} artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
