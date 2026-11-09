# /etc/systemd/system/app-{!! $clave !!}-scheduler.service
[Unit]
Description={!! $nombre !!} - scheduler (schedule:run)
PartOf=app-{!! $clave !!}.service

[Service]
Type=oneshot
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
ExecStart={!! $php !!} artisan schedule:run --no-interaction
