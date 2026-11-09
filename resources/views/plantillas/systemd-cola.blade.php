# /etc/systemd/system/app-{!! $clave !!}-queue.service
[Unit]
Description={!! $nombre !!} - worker de la cola
After=app-{!! $clave !!}.service
PartOf=app-{!! $clave !!}.service

[Service]
Type=simple
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
ExecStart={!! $php !!} {!! $ruta !!}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=app-{!! $clave !!}.service
