# /etc/systemd/system/app-{!! $clave !!}.service
# Generado por Consola Central (plantilla igual a app-gamtask.service) — revisar antes de instalar.
[Unit]
Description={!! $nombre !!} {!! $pruebas ? 'PRUEBAS' : 'PRODUCCION' !!} (Octane 127.0.0.1:{!! $puerto_interno !!})
After=network.target mariadb.service
Wants=app-{!! $clave !!}-queue.service app-{!! $clave !!}-scheduler.timer

[Service]
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
@if ($octane_servidor === 'roadrunner')
ExecStart={!! $php !!} artisan octane:start --server=roadrunner --host=127.0.0.1 --port={!! $puerto_interno !!} --rpc-host=127.0.0.1 --rpc-port={!! $puerto_rpc !!} --workers=auto --max-requests=500 --no-interaction
@else
ExecStart={!! $php !!} artisan octane:start --server={!! $octane_servidor !!} --host=127.0.0.1 --port={!! $puerto_interno !!} --workers=auto --max-requests=500 --no-interaction
@endif
ExecReload={!! $php !!} artisan octane:reload
Restart=always
RestartSec=5
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
