# /etc/systemd/system/app-{!! $clave !!}-scheduler.timer
[Unit]
Description={!! $nombre !!} - scheduler cada minuto
PartOf=app-{!! $clave !!}.service

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s

[Install]
WantedBy=timers.target app-{!! $clave !!}.service
