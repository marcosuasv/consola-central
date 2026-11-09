# /etc/systemd/system/app-{!! $clave !!}.service
# Generado por Consola Central — revisar antes de instalar.
[Unit]
Description={!! $nombre !!} (Octane 127.0.0.1:{!! $puerto_interno !!})
After=network-online.target mariadb.service
Wants=network-online.target

[Service]
Type=simple
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
ExecStart={!! $php !!} {!! $ruta !!}/artisan octane:start --server={!! $octane_servidor !!} --host=127.0.0.1 --port={!! $puerto_interno !!} --workers=auto
ExecReload={!! $php !!} {!! $ruta !!}/artisan octane:reload
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
