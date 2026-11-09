<?php

namespace Tests\Feature;

use App\Filament\Pages\Respaldos;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Models\Respaldo;
use App\Models\User;
use App\Servicios\Control\ConsolaCtl;
use App\Servicios\Respaldos\ServicioRespaldos;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class RespaldosTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'ab12cd34ef56ab12cd34ef56ab12cd34ef56ab12cd34ef56ab12cd34ef56ab12';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        // El seeder no toca estas columnas: se aplican los valores de la migración
        Aplicacion::where('es_pruebas', true)->update(['respaldo_activo' => false]);
        config(['database.connections.lectura.port' => '1', 'consola.respaldos.base_consola' => 'consola_central']);
        $this->fingir();
        Http::fake(['*' => Http::response('ok')]);
    }

    /** consola-ctl simulado: «rota» falla, las demás responden OK */
    private function fingir(): void
    {
        Process::fake(function (PendingProcess $p) {
            $c = $p->command;
            if ($c[0] !== 'sudo') {
                return Process::result("ActiveState=active\nSubState=running\nMainPID=10\nNRestarts=0");
            }
            [, , , $grupo, $accion, $base] = $c + [null, null, null, null, null, null];
            if ($grupo === 'respaldo' && $accion === 'crear') {
                return $base === 'rota'
                    ? Process::result('', 'ERROR: mysqldump falló para rota', 1)
                    : Process::result("OK archivo=/var/backups/consola/{$base}/{$base}-20261112-021700.sql.gz bytes=1048576 sha256=".self::SHA);
            }

            return Process::result('OK borrados=0');
        });
    }

    private function sudo(array $args): \Closure
    {
        return fn (PendingProcess $p) => $p->command === ['sudo', '-n', ConsolaCtl::BINARIO, ...$args];
    }

    public function test_objetivos_excluyen_pruebas_e_incluyen_la_consola(): void
    {
        $bases = array_column(app(ServicioRespaldos::class)->objetivos(), 'base');

        $this->assertSame(['GamTask', 'pca-gam', 'akoc-control', 'intranet_GBPO', 'consola_central'], $bases);
    }

    public function test_respaldo_correcto_se_registra_y_purga(): void
    {
        $app = Aplicacion::where('carpeta', 'GamTask')->first();

        $r = app(ServicioRespaldos::class)->respaldar($app, 'GamTask', 14);

        $this->assertTrue($r->exitoso());
        $this->assertSame(1048576, $r->tamano_bytes);
        $this->assertSame(self::SHA, $r->sha256);
        $this->assertStringEndsWith('GamTask-20261112-021700.sql.gz', $r->archivo);
        Process::assertRan($this->sudo(['respaldo', 'crear', 'GamTask']));
        Process::assertRan($this->sudo(['respaldo', 'purgar', 'GamTask', '14']));
        $this->assertSame(['respaldo.crear', 'respaldo.purgar'], Auditoria::orderBy('id')->pluck('accion')->all());
        $this->assertSame($app->id, Auditoria::first()->aplicacion_id);
    }

    public function test_respaldo_fallido_no_purga(): void
    {
        $r = app(ServicioRespaldos::class)->respaldar(null, 'rota', 14);

        $this->assertFalse($r->exitoso());
        $this->assertStringContainsString('mysqldump falló', $r->mensaje);
        Process::assertNotRan($this->sudo(['respaldo', 'purgar', 'rota', '14']));
        $this->assertSame('error', Auditoria::sole()->resultado);
    }

    public function test_nombre_invalido_no_llega_a_consola_ctl(): void
    {
        $r = app(ServicioRespaldos::class)->respaldar(null, 'x; DROP', 14);

        $this->assertFalse($r->exitoso());
        Process::assertNotRan(fn (PendingProcess $p) => $p->command[0] === 'sudo');
    }

    public function test_comando_nocturno_respalda_todas(): void
    {
        $this->artisan('consola:respaldar')->assertSuccessful();

        $this->assertSame(5, Respaldo::where('resultado', 'ok')->count());
        Process::assertRan($this->sudo(['respaldo', 'crear', 'consola_central']));
        Process::assertNotRan($this->sudo(['respaldo', 'crear', 'GamTask-pruebas']));

        $this->artisan('consola:respaldar', ['--base' => 'pca-gam'])->assertSuccessful();
        $this->assertSame(2, Respaldo::where('base_datos', 'pca-gam')->count());
    }

    public function test_alerta_amarilla_si_el_respaldo_esta_viejo(): void
    {
        $app = Aplicacion::where('carpeta', 'GamTask')->first();

        // Sin historial: no avisa (los respaldos aún no se instalan)
        $this->artisan('consola:recolectar', ['--app' => 'GamTask']);
        $this->assertSame('verde', $app->ultimoEstado()->first()->semaforo);

        // Último respaldo correcto hace 30 h: amarillo
        Respaldo::create(['base_datos' => 'GamTask', 'resultado' => 'ok', 'created_at' => now()->subHours(30)]);
        $this->artisan('consola:recolectar', ['--app' => 'GamTask']);
        $estado = $app->ultimoEstado()->first();
        $this->assertSame('amarillo', $estado->semaforo);
        $this->assertStringContainsString('Sin respaldo correcto', $estado->alertas[0]['mensaje']);

        // Respaldo reciente: vuelve a verde
        Respaldo::create(['base_datos' => 'GamTask', 'resultado' => 'ok', 'created_at' => now()->subHour()]);
        $this->artisan('consola:recolectar', ['--app' => 'GamTask']);
        $this->assertSame('verde', $app->ultimoEstado()->first()->semaforo);
    }

    public function test_lista_archivos_en_disco(): void
    {
        $carpeta = storage_path('framework/testing/respaldos');
        @mkdir($carpeta.'/GamTask', 0775, true);
        file_put_contents($carpeta.'/GamTask/GamTask-20261111-021700.sql.gz', str_repeat('x', 100));
        file_put_contents($carpeta.'/GamTask/GamTask-20261112-021700.sql.gz', str_repeat('x', 300));
        touch($carpeta.'/GamTask/GamTask-20261111-021700.sql.gz', time() - 86400);
        config(['consola.respaldos.carpeta' => $carpeta]);

        $archivos = app(ServicioRespaldos::class)->archivosEnDisco('GamTask');

        $this->assertCount(2, $archivos);
        $this->assertSame(300, $archivos[0]['bytes']); // el más reciente primero
        $this->assertNull(app(ServicioRespaldos::class)->archivosEnDisco('../etc'));
        $this->assertNull(app(ServicioRespaldos::class)->archivosEnDisco('NoExiste'));

        array_map('unlink', glob($carpeta.'/GamTask/*'));
    }

    public function test_pagina_y_respaldar_ahora(): void
    {
        $admin = User::factory()->create(['email' => 'admin@gbpo.com.mx', 'rol' => User::ROL_ADMIN]);
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->actingAs($admin);
        $pca = Aplicacion::where('carpeta', 'pca-gam')->first();

        Livewire::test(Respaldos::class)
            ->assertOk()
            ->assertSee('Todavía no hay respaldos')
            ->callTableAction('respaldarAhora', $pca)
            ->assertNotified('Respaldo de pca-gam listo');

        $r = Respaldo::sole();
        $this->assertSame('manual', $r->origen);
        $this->assertSame($admin->id, $r->user_id);

        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx']));
        Livewire::test(Respaldos::class)->assertOk()->assertTableActionHidden('respaldarAhora', $pca);
    }
}
