@php
    $caida = $tipo === 'caida';
    [$fondo, $borde, $texto] = $caida ? ['#ffe4e6', '#be123c', '#881337'] : ['#dcfce7', '#15803d', '#14532d'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $caida ? 'Falla' : 'Recuperación' }}: {{ $nombre }}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6">
<div style="max-width:600px;margin:0 auto;padding:20px 16px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">
    <div style="border-radius:6px;padding:14px 16px;background:{{ $fondo }};color:{{ $texto }};border-left:4px solid {{ $borde }}">
        <p style="font-size:18px;font-weight:bold;margin:0">{{ $caida ? 'En falla' : 'Recuperada' }}: {{ $nombre }}</p>
        <p style="margin:4px 0 0">
            @if ($caida)
                En rojo desde las {{ $incidente->inicio->format('H:i') }} del {{ $incidente->inicio->format('d/m/Y') }} ({{ $incidente->duracionMinutos() }} min).
            @else
                Volvió a funcionar a las {{ $incidente->fin?->format('H:i') }}. Estuvo en falla {{ $incidente->duracionMinutos() }} min (desde las {{ $incidente->inicio->format('H:i') }}).
            @endif
        </p>
    </div>

    @if ($incidente->motivos)
        <p style="font-weight:bold;margin:18px 0 6px">{{ $caida ? 'Qué se detectó' : 'Qué había fallado' }}</p>
        <ul style="margin:0;padding-left:18px">
            @foreach ($incidente->motivos as $m)
                <li>{{ $m }}</li>
            @endforeach
        </ul>
    @endif

    @if ($caida)
        <p style="font-weight:bold;margin:18px 0 6px">Qué hacer</p>
        <ol style="margin:0;padding-left:18px">
            <li>Abrir la Consola Central en el servidor (http://127.0.0.1:4900) y entrar al detalle de {{ $nombre }}.</li>
            <li>Revisar la pestaña Logs (systemd y Laravel) para ver el error.</li>
            <li>Si el servicio está detenido, usar Encender o Reiniciar.</li>
        </ol>
    @endif

    <div style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">
        Aviso inmediato de la Consola Central (DebWebServ, 10.2.0.170). Se envía cuando algo lleva {{ config('consola.correo.minutos_confirmacion') }} minutos o más en rojo{{ config('consola.correo.avisar_recuperacion') ? ' y cuando se recupera' : '' }}.
    </div>
</div>
</body>
</html>
