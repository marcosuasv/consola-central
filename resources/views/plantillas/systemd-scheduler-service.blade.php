# /etc/systemd/system/app-{!! $clave !!}-scheduler.service
[Unit]
Description={!! $nombre !!} {!! $pruebas ? 'PRUEBAS' : 'PRODUCCION' !!} - schedule:run

[Service]
Type=oneshot
User={!! $usuario !!}
Group={!! $usuario !!}
WorkingDirectory={!! $ruta !!}
ExecStart={!! $php !!} artisan schedule:run
