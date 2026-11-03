<?php

namespace Database\Seeders;

use App\Models\Aplicacion;
use Illuminate\Database\Seeder;

/**
 * Precarga las aplicaciones del servidor DebWebServ (10.2.0.170).
 *
 * Se puede volver a ejecutar: busca por carpeta y actualiza los datos de esta lista.
 * Ojo: sobrescribe los cambios hechos a mano en la consola para estos campos.
 */
class AplicacionesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->aplicaciones() as $orden => $datos) {
            Aplicacion::updateOrCreate(
                ['carpeta' => $datos['carpeta']],
                $datos + ['orden' => ($orden + 1) * 10],
            );
        }
    }

    private function aplicaciones(): array
    {
        return [
            $this->octane('GamTask', 'GamTask', 'gamtask', 18070, '443,4870,8070', 'GamTask', 'https://10.2.0.170/'),
            $this->octane('pca-gam', 'pca-gam', 'pcagam', 18089, '8089,4889', 'pca-gam', 'https://10.2.0.170:8089/', publica: true,
                notas: 'Única app publicada a internet. Usa el montaje CIFS /mnt/facturas_pdf.'),
            $this->octane('akoc-control', 'akoc-control', 'akoc', 18095, '8095,4995', 'akoc-control', 'https://10.2.0.170:8095/',
                notas: 'Tiene cambios sin commitear en el servidor: es esperado (aviso solo informativo en git status).'),
            [
                'nombre' => 'Intranet GBPO',
                'carpeta' => 'intranet_GBPO',
                'tipo' => Aplicacion::TIPO_FPM,
                'servicio' => null,
                'servicio_cola' => null,
                'timer_scheduler' => null,
                'pool_fpm' => 'intranet',
                'puerto_interno' => null,
                'puertos_publicos' => '8080',
                'url' => 'https://10.2.0.170:8080/',
                'url_salud' => 'https://127.0.0.1:8080/',
                'base_datos' => 'intranet_GBPO',
                'sesiones_en_bd' => false,
                'log_nginx' => '/var/log/nginx/app-intranet.access.log',
                'es_pruebas' => false,
                'arranque_automatico' => true,
                'publica_internet' => false,
                'monitorear' => true,
                'notas' => 'Sesiones en archivos (storage/framework/sessions). Certificado autofirmado: la prueba de salud no verifica TLS.',
            ],
            $this->octane('GamTask (pruebas)', 'GamTask-pruebas', 'gamtask-pruebas', 18075, '8075', 'GamTask-pruebas', 'https://10.2.0.170:8075/', pruebas: true),
            $this->octane('pca-gam (pruebas)', 'pca-gam-pruebas', 'pcagam-pruebas', 18088, '8088', 'pca-gam-pruebas', 'https://10.2.0.170:8088/', pruebas: true),
        ];
    }

    private function octane(
        string $nombre,
        string $carpeta,
        string $clave,
        int $puerto,
        string $publicos,
        string $baseDatos,
        string $url,
        bool $pruebas = false,
        bool $publica = false,
        ?string $notas = null,
    ): array {
        return [
            'nombre' => $nombre,
            'carpeta' => $carpeta,
            'tipo' => Aplicacion::TIPO_OCTANE,
            'servicio' => "app-{$clave}",
            'servicio_cola' => "app-{$clave}-queue",
            'timer_scheduler' => "app-{$clave}-scheduler.timer",
            'pool_fpm' => null,
            'puerto_interno' => $puerto,
            'puertos_publicos' => $publicos,
            'url' => $url,
            'url_salud' => "http://127.0.0.1:{$puerto}/",
            'base_datos' => $baseDatos,
            'sesiones_en_bd' => true,
            'log_nginx' => "/var/log/nginx/app-{$clave}.access.log",
            'es_pruebas' => $pruebas,
            'arranque_automatico' => ! $pruebas,
            'publica_internet' => $publica,
            'monitorear' => true,
            'notas' => $notas ?? ($pruebas ? 'Entorno de pruebas: no arranca con el servidor; apagada es normal.' : null),
        ];
    }
}
