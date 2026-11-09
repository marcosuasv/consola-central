# /etc/systemd/system/app-{!! $clave !!}.service
# Generado por Consola Central (plantilla basada en app-gamtask.service) — revisar antes de instalar.
[Unit]
Description={!! $nombre !!}{!! $pruebas ? ' PRUEBAS' : '' !!} (Octane 127.0.0.1:{!! $puerto_interno !!})
After=network.target mariadb.service
Wants=app-{!! $clave !!}-queue.service app-{!! $clave !!}-scheduler.timer

[Service]
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
ExecStart={!! $php !!} artisan octane:start --server={!! $octane_servidor !!} --host=127.0.0.1 --port={!! $puerto_interno !!}
ExecReload={!! $php !!} artisan octane:reload
Restart=always
RestartSec=5
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
