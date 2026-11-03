<?php

namespace Tests\Unit\Monitoreo;

use App\Models\Aplicacion;
use App\Servicios\Monitoreo\EvaluadorApp;
use App\Servicios\Monitoreo\Semaforo;
use Tests\TestCase;

class EvaluadorAppTest extends TestCase
{
    private function app(array $atributos = []): Aplicacion
    {
        return new Aplicacion($atributos + [
            'nombre' => 'X', 'carpeta' => 'x', 'tipo' => 'octane', 'servicio' => 'app-x',
            'servicio_cola' => 'app-x-queue', 'es_pruebas' => false, 'monitorear' => true,
        ]);
    }

    private function sana(array $cambios = []): array
    {
        return $cambios + [
            'servicio_estado' => 'active', 'cola_estado' => 'active', 'http_codigo' => 200, 'latencia_ms' => 150,
            'jobs_fallidos_24h' => 0, 'jobs_pendientes' => 0, 'reinicios_24h' => 0, 'errores_log_24h' => 0,
        ];
    }

    private function color(array $lectura, array $app = []): string
    {
        return (new EvaluadorApp)->evaluar($this->app($app), $lectura)['semaforo'];
    }

    public function test_reglas_del_semaforo(): void
    {
        $this->assertSame(Semaforo::VERDE, $this->color($this->sana()));
        $this->assertSame(Semaforo::VERDE, $this->color($this->sana(['http_codigo' => 302])));

        // Rojo
        $this->assertSame(Semaforo::ROJO, $this->color($this->sana(['servicio_estado' => 'failed'])));
        $this->assertSame(Semaforo::ROJO, $this->color($this->sana(['http_codigo' => 500])));
        $this->assertSame(Semaforo::ROJO, $this->color($this->sana(['http_codigo' => null])));
        $this->assertSame(Semaforo::ROJO, $this->color($this->sana(['jobs_fallidos_24h' => 6])));

        // Amarillo
        $this->assertSame(Semaforo::AMARILLO, $this->color($this->sana(['latencia_ms' => 2500])));
        $this->assertSame(Semaforo::AMARILLO, $this->color($this->sana(['reinicios_24h' => 1])));
        $this->assertSame(Semaforo::AMARILLO, $this->color($this->sana(['errores_log_24h' => 3])));
        $this->assertSame(Semaforo::AMARILLO, $this->color($this->sana(['jobs_pendientes' => 51])));
        $this->assertSame(Semaforo::AMARILLO, $this->color($this->sana(['jobs_fallidos_24h' => 2])));
        $this->assertSame(Semaforo::AMARILLO, $this->color($this->sana(['cola_estado' => 'inactive'])));

        // Gris
        $this->assertSame(Semaforo::GRIS, $this->color($this->sana(['servicio_estado' => 'inactive']), ['es_pruebas' => true]));
        $this->assertSame(Semaforo::GRIS, $this->color($this->sana(['servicio_estado' => 'failed']), ['monitorear' => false]));

        // Pruebas encendida se evalúa normal
        $this->assertSame(Semaforo::ROJO, $this->color($this->sana(['http_codigo' => 500]), ['es_pruebas' => true]));
    }

    public function test_peor_color(): void
    {
        $this->assertSame(Semaforo::ROJO, Semaforo::peor(Semaforo::VERDE, Semaforo::ROJO, Semaforo::AMARILLO));
        $this->assertSame(Semaforo::VERDE, Semaforo::peor(Semaforo::VERDE, Semaforo::GRIS));
    }
}
