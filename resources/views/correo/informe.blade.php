@php
    // Correo HTML simple con estilos en línea (Gmail y Outlook). Colores fijos: el correo siempre es claro.
    $colores = [
        'verde' => ['#dcfce7', '#15803d', 'Verde'],
        'amarillo' => ['#fef3c7', '#b45309', 'Amarillo'],
        'rojo' => ['#ffe4e6', '#be123c', 'Rojo'],
        'gris' => ['#f1f5f9', '#64748b', 'Sin datos'],
    ];
    $pildora = function (string $s) use ($colores) {
        [$fondo, $texto, $nombre] = $colores[$s] ?? $colores['gris'];

        return '<span style="display:inline-block;font-size:11px;font-weight:bold;padding:2px 8px;border-radius:10px;background:'.$fondo.';color:'.$texto.';white-space:nowrap">'.$nombre.'</span>';
    };
    $td = 'padding:8px;border-bottom:1px solid #e5e7eb;vertical-align:top;font-size:13px;color:#1f2937';
    $th = 'text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;padding:8px;border-bottom:2px solid #e5e7eb;background:#f9fafb';
    $chico = 'display:block;color:#6b7280;font-size:11px;margin-top:3px;line-height:1.4';
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Informe diario {{ $d['fecha'] }}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6">
<div style="max-width:760px;margin:0 auto;padding:20px 16px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">

    <p style="font-size:20px;font-weight:bold;margin:0 0 4px">Informe diario · DebWebServ</p>
    <p style="color:#6b7280;margin:0 0 18px">Periodo: {{ $d['desde'] }} a {{ $d['hasta'] }} · Servidor 10.2.0.170</p>

    @if ($d['urgentes'])
        <div style="border-radius:6px;padding:12px 14px;margin:0 0 20px;background:#ffe4e6;color:#881337;border-left:4px solid #be123c">
            <b>Requiere atención hoy:</b>
            <ul style="margin:6px 0 0;padding-left:18px">
                @foreach ($d['urgentes'] as $u)
                    <li>{{ $u }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div style="border-radius:6px;padding:12px 14px;margin:0 0 20px;background:#dcfce7;color:#14532d;border-left:4px solid #15803d">
            <b>Sin problemas críticos.</b> Revisa abajo los avisos en amarillo, si los hay.
        </div>
    @endif

    @unless ($d['completo'])
        <p style="font-size:12px;color:#b45309;margin:0 0 16px">Informe rápido: no incluye el análisis de vulnerabilidades ni de librerías (composer). El informe de las 07:00 sí lo incluye.</p>
    @endunless

    <p style="font-size:15px;font-weight:bold;margin:24px 0 8px">Semáforo por aplicación</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%">
        <thead>
            <tr>
                <th style="{{ $th }}">Aplicación</th>
                <th style="{{ $th }}">Operación 24 h</th>
                <th style="{{ $th }}">Ciberseguridad</th>
                <th style="{{ $th }}">Mantenimiento</th>
                <th style="{{ $th }}">Respaldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($d['apps'] as $a)
                <tr>
                    <td style="{{ $td }}"><b>{{ $a['nombre'] }}</b><span style="{{ $chico }}">{{ $a['detalle'] }}</span></td>
                    @foreach (['operacion', 'seguridad', 'mantenimiento', 'respaldo'] as $col)
                        <td style="{{ $td }}">{!! $pildora($a[$col]['semaforo']) !!}<span style="{{ $chico }}">{{ $a[$col]['texto'] }}</span></td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="5" style="{{ $td }}">No hay aplicaciones monitoreadas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="font-size:15px;font-weight:bold;margin:24px 0 8px">Servidor {!! $pildora($d['servidor']['semaforo']) !!}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%">
        @foreach (array_chunk($d['servidor']['datos'], 3) as $fila)
            <tr>
                @foreach ($fila as [$etiqueta, $valor, $sub])
                    <td style="padding:8px 10px;border:1px solid #e5e7eb;width:33%;vertical-align:top">
                        <span style="display:block;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">{{ $etiqueta }}</span>
                        <b style="font-size:16px">{{ $valor }}</b>
                        <span style="{{ $chico }}">{{ $sub }}</span>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <p style="font-size:15px;font-weight:bold;margin:24px 0 8px">Recomendaciones</p>
    @if ($d['recomendaciones'])
        <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%">
            @foreach ($d['recomendaciones'] as $r)
                <tr>
                    <td style="padding:6px 8px 6px 0;vertical-align:top;width:80px">{!! $pildora($r['nivel'] === 'gris' ? 'gris' : $r['nivel']) !!}</td>
                    <td style="padding:6px 0;vertical-align:top;font-size:13px;border-bottom:1px solid #f3f4f6"><b>{{ $r['sujeto'] }}:</b> {{ $r['texto'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="margin:0;color:#6b7280">No hay recomendaciones hoy.</p>
    @endif

    <div style="margin-top:28px;padding-top:14px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">
        Detalle completo en la Consola Central: http://127.0.0.1:4900 (solo desde el servidor).<br>
        Recibes este correo porque estás en la lista «Avisar» de la consola. Generado con reglas locales, sin IA.
    </div>
</div>
</body>
</html>
