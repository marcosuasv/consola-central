<?php

namespace Tests\Feature;

use App\Filament\Pages\Colas;
use App\Filament\Resources\Aplicaciones\Pages\MonitorAplicacion;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Models\User;
use App\Servicios\Control\ConsolaCtl;
use App\Servicios\Monitoreo\LecturaApps;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Paso 10: la conexión `lectura` se sustituye por SQLite con una base adjunta llamada GamTask,
 * para que las consultas calificadas (`GamTask`.failed_jobs) funcionen igual que en MariaDB.
 */
class ColasTest extends TestCase
{
    use RefreshDatabase;

    private Aplicacion $aplicacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        $this->aplicacion = Aplicacion::where('carpeta', 'GamTask')->first();

        config(['database.connections.lectura' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('lectura');
        $bd = DB::connection('lectura');
        $bd->statement("ATTACH DATABASE ':memory:' AS \"GamTask\"");
        $bd->statement('CREATE TABLE "GamTask".failed_jobs (id INTEGER PRIMARY KEY, uuid TEXT, connection TEXT, queue TEXT, payload TEXT, exception TEXT, failed_at TEXT)');
        $bd->statement('CREATE TABLE "GamTask".jobs (id INTEGER PRIMARY KEY, queue TEXT, payload TEXT, attempts INT, reserved_at INT, available_at INT, created_at INT)');

        $bd->table('GamTask.failed_jobs')->insert([
            [
                'uuid' => '8f14e45f-ceea-467f-a1b2-0123456789ab', 'connection' => 'database', 'queue' => 'default',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\EnviarFactura', 'data' => str_repeat('x', 5000)]),
                'exception' => "RuntimeException: SMTP caído password=Secreta123\n#0 /var/www/html/GamTask/app/Jobs/EnviarFactura.php(40)\n#1 {main}",
                'failed_at' => now()->subHour()->format('Y-m-d H:i:s'),
            ],
            [
                'uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'connection' => 'database', 'queue' => 'reportes',
                'payload' => '{"displayName":"App\\\\Jobs\\\\GenerarReporte","data":{"x":"', // recortado
                'exception' => 'Exception: tiempo agotado',
                'failed_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
            ],
        ]);
        $bd->table('GamTask.jobs')->insert([
            'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\Sincronizar']),
            'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp,
        ]);

        Process::fake(fn (PendingProcess $p) => $p->command[0] === 'sudo'
            ? Process::result('Reintentando job')
            : Process::result("ActiveState=active\nSubState=running\nMainPID=10\nNRestarts=0"));
        Http::fake(['*' => Http::response('ok')]);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@gbpo.com.mx', 'rol' => User::ROL_ADMIN]);
        $u->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $u;
    }

    public function test_lee_fallidos_con_nombre_error_y_secretos_ocultos(): void
    {
        $lectura = app(LecturaApps::class);
        $fallidos = $lectura->listaFallidos($this->aplicacion);

        $this->assertCount(2, $fallidos);
        $this->assertSame('EnviarFactura', $fallidos[0]['job']);
        $this->assertSame('GenerarReporte', $fallidos[1]['job']); // payload recortado: se lee displayName igual
        $this->assertStringStartsWith('RuntimeException: SMTP caído', $fallidos[0]['error']);
        $this->assertStringNotContainsString('Secreta123', $fallidos[0]['detalle']);
        $this->assertStringContainsString('#1 {main}', $fallidos[0]['detalle']);

        $this->assertSame(2, $lectura->totalFallidos($this->aplicacion));
        $this->assertSame(1, $lectura->fallidos24h($this->aplicacion));
        $this->assertSame('Sincronizar', $lectura->listaPendientes($this->aplicacion)[0]['job']);
    }

    public function test_reintentar_un_job_por_uuid(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MonitorAplicacion::class, ['record' => $this->aplicacion->id])
            ->set('pestana', 'colas')
            ->assertSee('EnviarFactura')
            ->callAction('reintentarJob', arguments: ['job' => '8f14e45f-ceea-467f-a1b2-0123456789ab', 'nombre' => 'EnviarFactura'])
            ->assertNotified('Job enviado de nuevo a la cola');

        Process::assertRan(fn (PendingProcess $p) => $p->command === [
            'sudo', '-n', ConsolaCtl::BINARIO, 'cola', 'reintentar', 'GamTask', '8f14e45f-ceea-467f-a1b2-0123456789ab',
        ]);

        $registro = Auditoria::where('accion', 'cola.reintentar')->sole();
        $this->assertSame($this->aplicacion->id, $registro->aplicacion_id);
        $this->assertSame('ok', $registro->resultado);
    }

    public function test_reintentar_todos_y_reiniciar_workers(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MonitorAplicacion::class, ['record' => $this->aplicacion->id])
            ->set('pestana', 'colas')
            ->callAction('reintentarTodos')
            ->callAction('reiniciarWorkers');

        Process::assertRan(fn (PendingProcess $p) => $p->command === ['sudo', '-n', ConsolaCtl::BINARIO, 'cola', 'reintentar', 'GamTask', 'all']);
        Process::assertRan(fn (PendingProcess $p) => $p->command === ['sudo', '-n', ConsolaCtl::BINARIO, 'cola', 'reiniciar', 'GamTask']);
        $this->assertSame(2, Auditoria::count());
    }

    public function test_identificador_invalido_no_llega_a_consola_ctl(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MonitorAplicacion::class, ['record' => $this->aplicacion->id])
            ->callAction('reintentarJob', arguments: ['job' => '1; rm -rf /'])
            ->assertNotified('No se pudo reintentar');

        Process::assertNotRan(fn (PendingProcess $p) => $p->command[0] === 'sudo');
        $this->assertSame(0, Auditoria::count());
    }

    public function test_lectura_no_ve_botones_de_reintento(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx']));

        Livewire::test(MonitorAplicacion::class, ['record' => $this->aplicacion->id])
            ->assertActionHidden('reintentarJob')
            ->assertActionHidden('reintentarTodos')
            ->assertActionHidden('reiniciarWorkers');
    }

    public function test_pagina_general_de_colas(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Colas::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Aplicacion::where('monitorear', true)->get())
            ->callTableAction('reintentarTodos', $this->aplicacion);

        Process::assertRan(fn (PendingProcess $p) => $p->command === ['sudo', '-n', ConsolaCtl::BINARIO, 'cola', 'reintentar', 'GamTask', 'all']);
    }

    public function test_el_monitor_abre_la_pestana_desde_la_url(): void
    {
        $this->actingAs($this->admin());

        $this->get('/aplicaciones/'.$this->aplicacion->id.'/monitor?pestana=colas')->assertOk()->assertSee('EnviarFactura');
        $this->get('/aplicaciones/'.$this->aplicacion->id.'/monitor?pestana=../../x')->assertOk();
    }
}
