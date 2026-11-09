<?php

namespace App\Servicios\Informes;

use App\Models\Aplicacion;
use App\Models\Incidente;
use App\Servicios\Monitoreo\Semaforo;

/**
 * Abre y cierra incidentes según el semáforo de cada lectura, y manda los avisos inmediatos.
 * Lo llama el recolector cada minuto.
 */
class GestorIncidentes
{
    public function __construct(private ServicioCorreo $correo) {}

    /**
     * @param  ?Aplicacion  $app  null = servidor
     * @param  list<array{nivel: string, mensaje: string}>  $alertas
     */
    public function procesar(?Aplicacion $app, string $semaforo, array $alertas): ?Incidente
    {
        // Las apps de pruebas no generan incidentes ni correos
        if ($app?->es_pruebas) {
            return null;
        }

        $abierto = Incidente::abiertos()->where('aplicacion_id', $app?->id)->latest('inicio')->first();

        if ($semaforo === Semaforo::ROJO) {
            $motivos = array_values(array_map(
                fn ($a) => $a['mensaje'],
                array_filter($alertas, fn ($a) => $a['nivel'] === Semaforo::ROJO),
            ));

            $abierto ??= Incidente::create(['aplicacion_id' => $app?->id, 'inicio' => now(), 'motivos' => $motivos]);

            $confirmado = $abierto->inicio->lte(now()->subMinutes((int) config('consola.correo.minutos_confirmacion', 2)));
            if ($abierto->avisado_en === null && $confirmado) {
                $abierto->forceFill(['motivos' => $motivos ?: $abierto->motivos, 'avisado_en' => now()])->save();
                $this->correo->avisarIncidente($abierto, 'caida');
            }

            return $abierto;
        }

        if ($abierto) {
            $abierto->forceFill(['fin' => now()])->save();

            // Solo se avisa la recuperación si antes se avisó la caída
            if ($abierto->avisado_en !== null && config('consola.correo.avisar_recuperacion', true)) {
                $abierto->forceFill(['recuperacion_avisada_en' => now()])->save();
                $this->correo->avisarIncidente($abierto, 'recuperacion');
            }
        }

        return $abierto;
    }
}
