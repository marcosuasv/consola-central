# /etc/systemd/system/app-{!! $clave !!}-scheduler.timer
# Sin [Install]: arranca por el Wants= de app-{!! $clave !!}.service.
[Unit]
Description={!! $nombre !!} {!! $pruebas ? 'PRUEBAS' : 'PRODUCCION' !!} - scheduler cada minuto
PartOf=app-{!! $clave !!}.service

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s
