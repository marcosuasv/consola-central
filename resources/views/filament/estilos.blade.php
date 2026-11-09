{{-- Estilos de la Consola Central. Usa las variables de color de Filament (claro y oscuro). --}}
<style>
    :root {
        --cc-verde: var(--success-500);
        --cc-amarillo: var(--warning-500);
        --cc-rojo: var(--danger-500);
        --cc-gris: var(--gray-400);
        --cc-superficie: #fff;
        --cc-borde: var(--gray-200);
        --cc-suave: var(--gray-50);
        --cc-texto: var(--gray-950);
        --cc-tenue: var(--gray-500);
        --cc-sombra: 0 1px 2px rgb(0 0 0 / .04), 0 1px 3px rgb(0 0 0 / .06);
    }
    .dark {
        --cc-superficie: var(--gray-900);
        --cc-borde: rgb(255 255 255 / .1);
        --cc-suave: rgb(255 255 255 / .04);
        --cc-texto: #fff;
        --cc-tenue: var(--gray-400);
        --cc-gris: var(--gray-500);
    }

    .cc-marca { display: flex; align-items: center; gap: .6rem; }
    .cc-marca-icono { width: 2rem; height: 2rem; flex: none; filter: drop-shadow(0 1px 2px rgb(29 78 216 / .25)); }
    .cc-marca-texto { display: flex; flex-direction: column; line-height: 1.1; }
    .cc-marca-nombre { font-weight: 700; font-size: 1rem; letter-spacing: -.01em; color: var(--cc-texto); }
    .cc-marca-sub { font-size: .66rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--cc-tenue); }
    .cc-rol { display: inline-flex; align-items: center; gap: .3rem; margin-inline-end: .75rem; padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; color: var(--info-700); background: color-mix(in oklab, var(--info-500) 12%, transparent); border: 1px solid color-mix(in oklab, var(--info-500) 30%, transparent); }
    .cc-rol-admin { color: var(--warning-700); background: color-mix(in oklab, var(--warning-500) 12%, transparent); border-color: color-mix(in oklab, var(--warning-500) 35%, transparent); }
    .dark .cc-rol { color: var(--info-300); }
    .dark .cc-rol-admin { color: var(--warning-300); }
    .cc-aviso-mfa { margin-bottom: 1rem; align-items: center; }
    .cc-aviso-mfa > div { flex: 1; }
    .cc-aviso-ambar { color: var(--warning-800); background: color-mix(in oklab, var(--cc-amarillo) 12%, transparent); border: 1px solid color-mix(in oklab, var(--cc-amarillo) 35%, transparent); }
    .dark .cc-aviso-ambar { color: var(--warning-200); }
    .cc-pie { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding: 1.25rem 2rem; font-size: .72rem; color: var(--cc-tenue); border-top: 1px solid var(--cc-borde); }
    .cc-leyenda { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }

    .cc-tablero, .cc-monitor { display: flex; flex-direction: column; gap: 1.25rem; }

    /* Píldoras y puntos de semáforo */
    .cc-punto { width: .5rem; height: .5rem; border-radius: 999px; background: currentColor; flex: none; }
    .cc-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap; background: var(--cc-suave); border: 1px solid var(--cc-borde); }
    .cc-pill.cc-verde { color: var(--success-700); background: color-mix(in oklab, var(--cc-verde) 12%, transparent); border-color: color-mix(in oklab, var(--cc-verde) 30%, transparent); }
    .cc-pill.cc-amarillo { color: var(--warning-700); background: color-mix(in oklab, var(--cc-amarillo) 14%, transparent); border-color: color-mix(in oklab, var(--cc-amarillo) 35%, transparent); }
    .cc-pill.cc-rojo { color: var(--danger-700); background: color-mix(in oklab, var(--cc-rojo) 12%, transparent); border-color: color-mix(in oklab, var(--cc-rojo) 30%, transparent); }
    .cc-pill.cc-gris { color: var(--cc-tenue); }
    .dark .cc-pill.cc-verde { color: var(--success-400); }
    .dark .cc-pill.cc-amarillo { color: var(--warning-400); }
    .dark .cc-pill.cc-rojo { color: var(--danger-400); }
    .cc-chip { display: inline-block; padding: 0 .4rem; border-radius: .3rem; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; background: var(--cc-suave); border: 1px solid var(--cc-borde); color: var(--cc-tenue); vertical-align: middle; }
    .cc-chip-aviso { color: var(--warning-700); border-color: color-mix(in oklab, var(--cc-amarillo) 40%, transparent); }
    .dark .cc-chip-aviso { color: var(--warning-400); }

    .cc-texto-amarillo { color: var(--warning-600) !important; }
    .cc-texto-rojo { color: var(--danger-600) !important; }
    .dark .cc-texto-amarillo { color: var(--warning-400) !important; }
    .dark .cc-texto-rojo { color: var(--danger-400) !important; }
    .cc-fondo-verde { background: var(--cc-verde); }
    .cc-fondo-amarillo { background: var(--cc-amarillo); }
    .cc-fondo-rojo { background: var(--cc-rojo); }
    .cc-fondo-gris { background: var(--cc-gris); }
    .cc-fondo-vacio { background: var(--cc-borde); }

    .cc-ico { width: 1.25rem; height: 1.25rem; flex: none; }
    .cc-ico-chico { width: 1rem; height: 1rem; display: inline; }
    .cc-ico-grande { width: 2.5rem; height: 2.5rem; color: var(--cc-tenue); }

    /* Resumen superior */
    .cc-resumen { display: flex; flex-wrap: wrap; gap: 1px; background: var(--cc-borde); border: 1px solid var(--cc-borde); border-radius: .75rem; overflow: hidden; box-shadow: var(--cc-sombra); }
    .cc-resumen-item { flex: 1 1 10rem; display: flex; flex-direction: column; gap: .35rem; padding: .9rem 1.1rem; background: var(--cc-superficie); }
    .cc-resumen-etiqueta, .cc-metrica-etiqueta { font-size: .72rem; font-weight: 500; color: var(--cc-tenue); text-transform: uppercase; letter-spacing: .04em; }
    .cc-resumen-valor { font-size: 1.6rem; font-weight: 700; line-height: 1.1; color: var(--cc-texto); font-variant-numeric: tabular-nums; }
    .cc-resumen-sub { font-size: .9rem; font-weight: 600; color: var(--cc-texto); }
    .cc-resumen-semaforos { display: flex; gap: .35rem; flex-wrap: wrap; }

    .cc-aviso { display: flex; gap: .75rem; align-items: flex-start; padding: .85rem 1rem; border-radius: .75rem; font-size: .875rem; }
    .cc-aviso-rojo { color: var(--danger-700); background: color-mix(in oklab, var(--cc-rojo) 10%, transparent); border: 1px solid color-mix(in oklab, var(--cc-rojo) 30%, transparent); }
    .dark .cc-aviso-rojo { color: var(--danger-300); }
    .cc-aviso code, .cc-nota code, .cc-ficha code, .cc-vacio code, .cc-sin-datos code { font-size: .8em; padding: .05rem .3rem; border-radius: .25rem; background: var(--cc-suave); border: 1px solid var(--cc-borde); }

    /* Sistema */
    .cc-sistema .cc-sis-tarjeta { box-shadow: none; background: var(--cc-suave); }
    .cc-sistema { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); }
    .cc-sis-tarjeta, .cc-kpi, .cc-caja { display: flex; flex-direction: column; gap: .35rem; padding: 1rem 1.1rem; background: var(--cc-superficie); border: 1px solid var(--cc-borde); border-radius: .75rem; box-shadow: var(--cc-sombra); min-width: 0; }
    .cc-sis-ancha { grid-column: 1 / -1; }
    .cc-sis-valor { font-size: 1.4rem; font-weight: 700; color: var(--cc-texto); font-variant-numeric: tabular-nums; }
    .cc-sis-sub { font-size: .78rem; color: var(--cc-tenue); }
    .cc-sis-alertas { margin-top: .4rem; display: flex; flex-direction: column; gap: .25rem; }
    .cc-servicios { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .2rem; }
    .cc-barra { height: .4rem; border-radius: 999px; background: var(--cc-suave); border: 1px solid var(--cc-borde); overflow: hidden; }
    .cc-barra-relleno { height: 100%; border-radius: 999px; transition: width .3s ease; }

    /* Encabezado de sección */
    .cc-encabezado-seccion { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: .25rem; }
    .cc-encabezado-seccion h2 { font-size: 1.05rem; font-weight: 600; color: var(--cc-texto); }
    .cc-interruptor { display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; color: var(--cc-tenue); cursor: pointer; user-select: none; }
    .cc-interruptor input { accent-color: var(--primary-500); width: 1rem; height: 1rem; }

    /* Tarjetas de app */
    .cc-rejilla { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 21rem), 1fr)); }
    .cc-tarjeta { position: relative; display: flex; flex-direction: column; gap: .9rem; padding: 1.1rem 1.2rem 1rem; background: var(--cc-superficie); border: 1px solid var(--cc-borde); border-radius: .9rem; box-shadow: var(--cc-sombra); overflow: hidden; transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease; color: inherit; text-decoration: none; }
    .cc-tarjeta::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 4px; background: var(--cc-gris); }
    .cc-borde-verde::before { background: var(--cc-verde); }
    .cc-borde-amarillo::before { background: var(--cc-amarillo); }
    .cc-borde-rojo::before { background: var(--cc-rojo); }
    .cc-tarjeta:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgb(15 23 42 / .08); border-color: color-mix(in oklab, var(--primary-500) 40%, var(--cc-borde)); }
    .cc-tarjeta:focus-within { border-color: var(--primary-500); }
    /* Enlace "estirado": toda la tarjeta abre el detalle; los controles quedan por encima */
    .cc-enlace-tarjeta { color: inherit; outline: none; }
    .cc-enlace-tarjeta::after { content: ""; position: absolute; inset: 0; z-index: 1; border-radius: inherit; }
    .cc-enlace-tarjeta:focus-visible::after { outline: 2px solid var(--primary-500); outline-offset: 2px; }
    .cc-controles { position: relative; z-index: 2; display: flex; gap: .15rem; padding: .15rem; border-radius: .6rem; background: var(--cc-suave); border: 1px solid var(--cc-borde); }
    .cc-ico-mini { width: .75rem; height: .75rem; display: inline; vertical-align: -1px; }
    @media (prefers-reduced-motion: reduce) { .cc-tarjeta, .cc-barra-relleno { transition: none; } .cc-tarjeta:hover { transform: none; } }
    .cc-tarjeta-cabeza { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
    .cc-tarjeta-titulo { min-width: 0; }
    .cc-tarjeta-titulo h3 { font-size: 1.05rem; font-weight: 650; color: var(--cc-texto); line-height: 1.3; }
    .cc-tarjeta-titulo p { font-size: .78rem; color: var(--cc-tenue); margin-top: .15rem; display: flex; gap: .35rem; flex-wrap: wrap; align-items: center; }
    .cc-metricas { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem 1rem; padding: .85rem 0; border-block: 1px solid var(--cc-borde); }
    .cc-metrica { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
    .cc-metrica-valor { font-size: .98rem; font-weight: 650; color: var(--cc-texto); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cc-metrica-valor small { font-size: .72rem; font-weight: 600; }
    .cc-alertas { display: flex; flex-direction: column; gap: .35rem; }
    .cc-alerta { display: flex; align-items: baseline; gap: .5rem; font-size: .8rem; color: var(--cc-texto); line-height: 1.35; }
    .cc-alerta .cc-punto { transform: translateY(-.05rem); }
    .cc-alerta.cc-verde .cc-punto { color: var(--cc-verde); }
    .cc-alerta.cc-amarillo .cc-punto { color: var(--cc-amarillo); }
    .cc-alerta.cc-rojo .cc-punto { color: var(--cc-rojo); }
    .cc-alerta.cc-gris .cc-punto { color: var(--cc-gris); }
    .cc-alerta-grande { font-size: .875rem; padding: .55rem .7rem; border-radius: .5rem; background: var(--cc-suave); border: 1px solid var(--cc-borde); }
    .cc-alerta-mas { font-size: .75rem; color: var(--cc-tenue); padding-left: 1rem; }
    .cc-tarjeta-pie { display: flex; justify-content: space-between; align-items: center; font-size: .75rem; color: var(--cc-tenue); margin-top: auto; }
    .cc-ver { display: inline-flex; align-items: center; gap: .25rem; font-weight: 600; color: var(--primary-600); }
    .dark .cc-ver { color: var(--primary-400); }
    .cc-sin-datos { font-size: .85rem; color: var(--cc-tenue); padding: .75rem 0; }
    .cc-vacio { display: flex; flex-direction: column; align-items: center; gap: .6rem; text-align: center; padding: 2.5rem 1rem; color: var(--cc-tenue); font-size: .9rem; }
    .cc-vacio p { color: var(--cc-texto); font-weight: 600; }

    /* Monitor de app */
    .cc-estado-cabeza { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.2rem; background: var(--cc-superficie); border: 1px solid var(--cc-borde); border-radius: .9rem; box-shadow: var(--cc-sombra); overflow: hidden; }
    .cc-estado-cabeza::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 5px; background: var(--cc-gris); }
    .cc-estado-cabeza.cc-borde-verde::before { background: var(--cc-verde); }
    .cc-estado-cabeza.cc-borde-amarillo::before { background: var(--cc-amarillo); }
    .cc-estado-cabeza.cc-borde-rojo::before { background: var(--cc-rojo); }
    .cc-estado-semaforo, .cc-version { display: flex; align-items: center; gap: .9rem; }
    .cc-foco { width: 1.1rem; height: 1.1rem; border-radius: 999px; background: var(--cc-gris); box-shadow: 0 0 0 5px color-mix(in oklab, var(--cc-gris) 20%, transparent); flex: none; }
    .cc-foco.cc-verde { background: var(--cc-verde); box-shadow: 0 0 0 5px color-mix(in oklab, var(--cc-verde) 22%, transparent); }
    .cc-foco.cc-amarillo { background: var(--cc-amarillo); box-shadow: 0 0 0 5px color-mix(in oklab, var(--cc-amarillo) 25%, transparent); }
    .cc-foco.cc-rojo { background: var(--cc-rojo); box-shadow: 0 0 0 5px color-mix(in oklab, var(--cc-rojo) 22%, transparent); }
    .cc-estado-titulo { font-size: 1.15rem; font-weight: 700; color: var(--cc-texto); }
    .cc-estado-sub { font-size: .8rem; color: var(--cc-tenue); }
    .cc-unidades { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
    .cc-kpis { display: grid; gap: .75rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    @media (max-width: 64rem) { .cc-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cc-kpi-valor { font-size: 1.35rem; font-weight: 700; color: var(--cc-texto); font-variant-numeric: tabular-nums; }
    .cc-panel { display: flex; flex-direction: column; gap: 1rem; }
    .cc-dos-columnas { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr)); align-items: start; }
    .cc-caja-titulo { font-size: .9rem; font-weight: 600; color: var(--cc-texto); margin-bottom: .35rem; }
    .cc-mt { margin-top: 1rem; }
    .cc-nota { font-size: .8rem; color: var(--cc-tenue); }
    .cc-ficha { display: grid; grid-template-columns: max-content 1fr; gap: .45rem 1rem; font-size: .85rem; }
    .cc-ficha dt { color: var(--cc-tenue); }
    .cc-ficha dd { color: var(--cc-texto); min-width: 0; overflow-wrap: anywhere; }
    .cc-linea-tiempo { display: grid; grid-template-columns: repeat(96, 1fr); gap: 2px; height: 2rem; }
    .cc-bloque { border-radius: 2px; }
    .cc-linea-tiempo-pie { display: flex; justify-content: space-between; font-size: .72rem; color: var(--cc-tenue); margin-top: .35rem; }
    .cc-graficas { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); }
    .cc-grafica-cabeza { display: flex; justify-content: space-between; align-items: baseline; }
    .cc-sparkline { width: 100%; height: 4rem; overflow: visible; }
    .cc-sparkline path { fill: none; stroke: var(--primary-500); stroke-width: 1.6; vector-effect: non-scaling-stroke; stroke-linejoin: round; stroke-linecap: round; }

    /* Tablas */
    .cc-tabla-contenedor { overflow-x: auto; border: 1px solid var(--cc-borde); border-radius: .75rem; background: var(--cc-superficie); }
    .cc-tabla { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .cc-tabla th { text-align: left; font-weight: 600; font-size: .75rem; color: var(--cc-tenue); padding: .6rem .9rem; background: var(--cc-suave); border-bottom: 1px solid var(--cc-borde); white-space: nowrap; }
    .cc-tabla td { padding: .6rem .9rem; border-bottom: 1px solid var(--cc-borde); color: var(--cc-texto); vertical-align: top; }
    .cc-tabla tr:last-child td { border-bottom: 0; }
    .cc-avatar { display: inline-grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 999px; margin-right: .4rem; font-size: .72rem; font-weight: 700; color: var(--primary-700); background: color-mix(in oklab, var(--primary-500) 18%, transparent); }
    .cc-error-corto { font-family: ui-monospace, monospace; font-size: .75rem; overflow-wrap: anywhere; }

    /* Colas */
    .cc-texto-verde { color: var(--success-600) !important; }
    .cc-barra-cola { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; padding: .75rem 1rem; background: var(--cc-superficie); border: 1px solid var(--cc-borde); border-radius: .75rem; box-shadow: var(--cc-sombra); }
    .cc-barra-cola-datos, .cc-barra-cola-acciones { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
    .cc-lista-fallidos { display: flex; flex-direction: column; border: 1px solid var(--cc-borde); border-radius: .75rem; overflow: hidden; }
    .cc-fallido + .cc-fallido { border-top: 1px solid var(--cc-borde); }
    .cc-fallido summary { display: flex; justify-content: space-between; gap: .75rem; padding: .65rem .85rem; cursor: pointer; list-style: none; }
    .cc-fallido summary::-webkit-details-marker { display: none; }
    .cc-fallido summary:hover { background: var(--cc-suave); }
    .cc-fallido[open] summary { background: var(--cc-suave); }
    .cc-fallido-info { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
    .cc-fallido-job { font-weight: 600; font-size: .875rem; color: var(--cc-texto); }
    .cc-fallido-acciones { flex: none; align-self: center; }
    .cc-fallido-detalle { margin: 0; border-radius: 0; border: 0; max-height: 18rem; }

    /* Logs */
    .cc-filtros { display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; align-items: center; }
    .cc-filtros-derecha { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    .cc-segmentado { display: inline-flex; flex-wrap: wrap; padding: .2rem; gap: .2rem; border-radius: .6rem; background: var(--cc-suave); border: 1px solid var(--cc-borde); }
    .cc-segmentado button { padding: .35rem .7rem; border-radius: .45rem; font-size: .8rem; font-weight: 500; color: var(--cc-tenue); }
    .cc-segmentado button:hover { color: var(--cc-texto); }
    .cc-segmentado button.cc-activo { background: var(--cc-superficie); color: var(--cc-texto); box-shadow: var(--cc-sombra); font-weight: 600; }
    .cc-segmentado button:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 1px; }
    .cc-en-vivo { display: inline-block; width: .5rem; height: .5rem; border-radius: 999px; background: var(--cc-rojo); margin-right: .3rem; animation: cc-latido 1.4s ease-in-out infinite; }
    @keyframes cc-latido { 50% { opacity: .3; } }
    @media (prefers-reduced-motion: reduce) { .cc-en-vivo { animation: none; } }
    .cc-log { max-height: 34rem; overflow: auto; border-radius: .75rem; background: #0b1020; color: #d6deeb; border: 1px solid var(--cc-borde); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .76rem; line-height: 1.55; }
    .cc-log-crudo { padding: .8rem 1rem; white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; }
    .cc-log-entrada { border-bottom: 1px solid rgb(255 255 255 / .06); }
    .cc-log-entrada summary { display: flex; gap: .6rem; padding: .4rem .9rem; cursor: pointer; list-style: none; align-items: baseline; }
    .cc-log-entrada summary::-webkit-details-marker { display: none; }
    .cc-log-entrada summary:hover { background: rgb(255 255 255 / .04); }
    .cc-log-entrada pre { margin: 0; padding: .3rem .9rem .7rem 1.6rem; white-space: pre-wrap; overflow-wrap: anywhere; color: #9aa6bf; }
    .cc-log-nivel { flex: none; min-width: 4.8rem; font-weight: 700; color: #7fdbca; }
    .cc-log-fecha { flex: none; color: #8792a8; }
    .cc-log-texto { min-width: 0; overflow-wrap: anywhere; }
    .cc-log-error .cc-log-nivel, .cc-log-critical .cc-log-nivel, .cc-log-alert .cc-log-nivel, .cc-log-emergency .cc-log-nivel { color: #ff6b81; }
    .cc-log-warning .cc-log-nivel { color: #ffcb6b; }
    .cc-log-debug .cc-log-nivel { color: #8792a8; }
</style>
