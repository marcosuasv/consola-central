<?php

namespace Tests\Feature;

use App\Filament\Pages\Tablero;
use App\Filament\Resources\Aplicaciones\Pages\MonitorAplicacion;
use App\Filament\Widgets\ResumenGeneral;
use App\Models\Aplicacion;
use App\Models\EstadoApp;
use App\Models\EstadoSistema;
use App\Models\User;
use App\Servicios\Monitoreo\Semaforo;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoreoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        // Sin servidor de BD de las apps en pruebas: la conexión de lectura falla y debe dar "sin datos"
        config(['database.connections.lectura.host' => '127.0.0.1', 'database.connections.lectura.port' => '1']);
    }

    private function fingirServidor(string $estadoApps = 'active', int $http = 200): void
    {
        Process::fake(function (PendingProcess $proceso) use ($estadoApps) {
            $comando = $proceso->command;

            if ($comando[0] === 'systemctl') {
                $unidad = $comando[2];
                $estado = str_starts_with($unidad, 'app-') ? $estadoApps : 'active';

                return Process::result(implode("\n", [
                    "ActiveState={$estado}",
                    'SubState='.($estado === 'active' ? 'running' : 'dead'),
                    'MainPID='.($estado === 'active' ? '4242' : '0'),
                    'MemoryCurrent=314572800',
                    'CPUUsageNSec=123456789',
                    'ActiveEnterTimestamp=Tue 2026-11-03 15:05:03 CST',
                    'NRestarts=0',
                ]));
            }

            if ($comando[0] === 'findmnt') {
                return Process::result('/mnt/facturas_pdf');
            }

            return Process::result('', '', 1);
        });

        Http::fake(['*' => Http::response('ok', $http)]);
    }

    public function test_el_recolector_guarda_estado_de_apps_y_sistema(): void
    {
        $this->fingirServidor();

        $this->artisan('consola:recolectar')->assertSuccessful();

        $this->assertSame(6, EstadoApp::count());
        $this->assertSame(1, EstadoSistema::count());

        $gamtask = Aplicacion::where('carpeta', 'GamTask')->first()->ultimoEstado;
        $this->assertSame('active', $gamtask->servicio_estado);
        $this->assertSame(4242, $gamtask->pid);
        $this->assertSame(314572800, $gamtask->memoria_bytes);
        $this->assertSame(200, $gamtask->http_codigo);
        $this->assertNull($gamtask->usuarios_conectados); // sin BD de la app: "sin datos", no error
        $this->assertSame(Semaforo::VERDE, $gamtask->semaforo);
        $this->assertSame('2026-11-03 15:05:03', $gamtask->activo_desde->format('Y-m-d H:i:s'));

        Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'systemctl' && $p->command[2] === 'app-gamtask');
        Http::assertSent(fn ($r) => $r->url() === 'http://127.0.0.1:18070/');
    }

    public function test_app_caida_en_rojo_y_pruebas_apagada_en_gris(): void
    {
        $this->fingirServidor('inactive', 502);

        $this->artisan('consola:recolectar')->assertSuccessful();

        $produccion = Aplicacion::where('carpeta', 'GamTask')->first()->ultimoEstado;
        $this->assertSame(Semaforo::ROJO, $produccion->semaforo);
        $mensajes = array_column($produccion->alertas, 'mensaje');
        $this->assertContains('El servicio está detenido.', $mensajes);
        $this->assertContains('La prueba de salud respondió HTTP 502.', $mensajes);

        $pruebas = Aplicacion::where('carpeta', 'GamTask-pruebas')->first()->ultimoEstado;
        $this->assertSame(Semaforo::GRIS, $pruebas->semaforo);
    }

    public function test_el_recolector_purga_registros_viejos(): void
    {
        $this->fingirServidor();
        $app = Aplicacion::first();
        $app->estados()->create(['semaforo' => 'verde', 'created_at' => now()->subDays(8)]);

        $this->artisan('consola:recolectar')->assertSuccessful();

        $this->assertSame(0, EstadoApp::where('created_at', '<', now()->subDays(7))->count());
    }

    public function test_tablero_y_monitor_se_muestran(): void
    {
        $this->fingirServidor();
        $this->artisan('consola:recolectar');
        $this->actingAs(User::factory()->create(['email' => 'admin@gbpo.com.mx']));

        Livewire::test(Tablero::class)
            ->assertOk()
            ->assertSee('GamTask')
            ->assertSee('Operando')
            ->set('mostrarPruebas', false)
            ->assertDontSee('GamTask (pruebas)');

        $app = Aplicacion::where('carpeta', 'pca-gam')->first();
        $pagina = Livewire::test(MonitorAplicacion::class, ['record' => $app->id])->assertOk()->assertSee('pca-gam');

        foreach (array_keys(MonitorAplicacion::PESTANAS) as $pestana) {
            $pagina->call('cambiarPestana', $pestana)->assertOk()->assertSet('pestana', $pestana);
        }

        foreach (array_keys(MonitorAplicacion::FUENTES_LOG) as $fuente) {
            $pagina->call('cambiarPestana', 'logs')->set('fuente', $fuente)->assertOk();
        }

        // Valores fuera de lista se corrigen
        $pagina->set('fuente', '../../etc/passwd')->assertSet('fuente', 'laravel')
            ->set('lineas', 99999)->assertSet('lineas', 500);
    }

    public function test_resumen_cuenta_amarillas_como_en_linea(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'admin@gbpo.com.mx']));
        $colores = ['GamTask' => 'verde', 'pca-gam' => 'amarillo', 'akoc-control' => 'rojo'];
        foreach (Aplicacion::all() as $app) {
            $app->estados()->create(['semaforo' => $colores[$app->carpeta] ?? 'gris', 'created_at' => now()]);
        }

        Livewire::test(ResumenGeneral::class)
            ->assertSee('Aplicaciones en línea')
            ->assertSee('2 de 3')
            ->assertSee('1 con falla · 1 con avisos');
    }

    public function test_tablero_avisa_si_no_hay_lecturas_recientes(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'admin@gbpo.com.mx']));

        Livewire::test(Tablero::class)->assertSee('Los datos no están al día');
    }
}
