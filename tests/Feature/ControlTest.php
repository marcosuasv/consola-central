<?php

namespace Tests\Feature;

use App\Filament\Pages\Tablero;
use App\Filament\Resources\Aplicaciones\Pages\MonitorAplicacion;
use App\Filament\Resources\Auditorias\Pages\ListAuditorias;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Models\User;
use App\Servicios\Control\ConsolaCtl;
use App\Servicios\Control\ControlAplicacion;
use Database\Seeders\AplicacionesSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class ControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        config(['database.connections.lectura.port' => '1']);

        Process::fake(function (PendingProcess $p) {
            if ($p->command[0] === 'sudo') {
                return Process::result('ok');
            }

            return Process::result("ActiveState=active\nSubState=running\nMainPID=10\nNRestarts=0");
        });
        Http::fake(['*' => Http::response('ok')]);
    }

    private function admin(bool $mfa = true): User
    {
        $u = User::factory()->create(['email' => 'admin@gbpo.com.mx', 'rol' => User::ROL_ADMIN]);
        if ($mfa) {
            $u->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        }

        return $u;
    }

    private function sudoCon(array $argumentos): \Closure
    {
        return fn (PendingProcess $p) => $p->command === ['sudo', '-n', ConsolaCtl::BINARIO, ...$argumentos];
    }

    public function test_reiniciar_llama_a_consola_ctl_con_arreglo_y_audita(): void
    {
        $this->actingAs($this->admin());
        $app = Aplicacion::where('carpeta', 'GamTask')->first();

        $resultados = app(ControlAplicacion::class)->ejecutar($app, ControlAplicacion::REINICIAR);

        $this->assertTrue($resultados[0]->ok);
        Process::assertRan($this->sudoCon(['servicio', 'restart', 'app-gamtask']));

        $registro = Auditoria::sole();
        $this->assertSame('servicio.restart', $registro->accion);
        $this->assertSame('app-gamtask', $registro->objetivo);
        $this->assertSame($app->id, $registro->aplicacion_id);
        $this->assertSame('ok', $registro->resultado);
        $this->assertNotNull($app->fresh()->ultimoEstado); // se tomó una lectura nueva
    }

    public function test_encender_arranca_app_cola_y_scheduler(): void
    {
        $this->actingAs($this->admin());
        $app = Aplicacion::where('carpeta', 'pca-gam')->first();

        app(ControlAplicacion::class)->ejecutar($app, ControlAplicacion::ENCENDER);

        Process::assertRan($this->sudoCon(['servicio', 'start', 'app-pcagam']));
        Process::assertRan($this->sudoCon(['servicio', 'start', 'app-pcagam-queue']));
        Process::assertRan($this->sudoCon(['servicio', 'start', 'app-pcagam-scheduler.timer']));
        $this->assertSame(3, Auditoria::count());
    }

    public function test_fpm_solo_recarga_y_reinicia_cola(): void
    {
        $this->actingAs($this->admin());
        $intranet = Aplicacion::where('carpeta', 'intranet_GBPO')->first();

        $this->assertSame([ControlAplicacion::RECARGAR, ControlAplicacion::REINICIAR_COLA], ControlAplicacion::disponibles($intranet));

        app(ControlAplicacion::class)->ejecutar($intranet, ControlAplicacion::RECARGAR);
        Process::assertRan($this->sudoCon(['fpm', 'reload']));

        $this->expectException(\InvalidArgumentException::class);
        app(ControlAplicacion::class)->ejecutar($intranet, ControlAplicacion::APAGAR);
    }

    public function test_los_errores_tambien_se_auditan(): void
    {
        Process::fake(['*' => Process::result('', 'ERROR: unidad no permitida', 2)]);
        $this->actingAs($this->admin());

        $r = app(ConsolaCtl::class)->servicio('stop', 'app-gamtask');

        $this->assertFalse($r->ok);
        $this->assertSame('ERROR: unidad no permitida', $r->resumen());
        $this->assertSame('error', Auditoria::sole()->resultado);
        $this->assertSame(2, Auditoria::sole()->codigo_salida);
    }

    public function test_consola_ctl_rechaza_valores_invalidos_antes_de_llamar(): void
    {
        $ctl = app(ConsolaCtl::class);

        foreach ([
            fn () => $ctl->servicio('stop', 'ssh'),
            fn () => $ctl->servicio('stop', 'app-gamtask;id'),
            fn () => $ctl->servicio('kill', 'app-gamtask'),
            fn () => $ctl->colaReiniciar('../etc'),
            fn () => $ctl->colaReintentar('GamTask', '1; rm -rf /'),
        ] as $llamada) {
            try {
                $llamada();
                $this->fail('Debió rechazarse');
            } catch (\InvalidArgumentException) {
                // esperado
            }
        }

        Process::assertNothingRan();
        $this->assertSame(0, Auditoria::count());
    }

    public function test_solo_admin_puede_controlar(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx', 'rol' => User::ROL_LECTURA]));

        $this->expectException(AuthorizationException::class);
        app(ControlAplicacion::class)->ejecutar(Aplicacion::first(), ControlAplicacion::REINICIAR);
    }

    public function test_botones_en_el_monitor_segun_rol_y_mfa(): void
    {
        $app = Aplicacion::where('carpeta', 'GamTask')->first();

        // Lectura: no ve botones
        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx', 'rol' => User::ROL_LECTURA]));
        Livewire::test(MonitorAplicacion::class, ['record' => $app->id])
            ->assertActionHidden('reiniciar');

        // Admin sin MFA: ve botones deshabilitados
        $this->actingAs($this->admin(mfa: false));
        Livewire::test(MonitorAplicacion::class, ['record' => $app->id])
            ->assertActionVisible('reiniciar')
            ->assertActionDisabled('reiniciar');
    }

    public function test_apagar_produccion_exige_escribir_el_nombre(): void
    {
        $this->actingAs($this->admin());
        $app = Aplicacion::where('carpeta', 'GamTask')->first();
        $app->estados()->create(['servicio_estado' => 'active', 'semaforo' => 'verde', 'created_at' => now()]);

        Livewire::test(MonitorAplicacion::class, ['record' => $app->id])
            ->callAction('apagar', data: ['confirmacion' => 'otra cosa'])
            ->assertHasActionErrors(['confirmacion']);

        Process::assertNotRan($this->sudoCon(['servicio', 'stop', 'app-gamtask']));

        Livewire::test(MonitorAplicacion::class, ['record' => $app->id])
            ->callAction('apagar', data: ['confirmacion' => 'GamTask'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        Process::assertRan($this->sudoCon(['servicio', 'stop', 'app-gamtask']));
    }

    public function test_reiniciar_desde_la_tarjeta_del_tablero(): void
    {
        $this->actingAs($this->admin());
        $pruebas = Aplicacion::where('carpeta', 'GamTask-pruebas')->first();

        // App de pruebas: no pide escribir el nombre
        Livewire::test(Tablero::class)
            ->callAction('controlar', arguments: ['aplicacion' => $pruebas->id, 'accion' => 'reiniciar'])
            ->assertHasNoActionErrors();

        Process::assertRan($this->sudoCon(['servicio', 'restart', 'app-gamtask-pruebas']));
    }

    public function test_acceso_al_panel(): void
    {
        $panel = Filament::getPanel('consola');

        $this->assertTrue(User::factory()->make(['email' => 'a@gbpo.com.mx', 'activo' => true])->canAccessPanel($panel));
        $this->assertFalse(User::factory()->make(['email' => 'a@gbpo.com.mx', 'activo' => false])->canAccessPanel($panel));
        $this->assertFalse(User::factory()->make(['email' => 'a@gmail.com', 'activo' => true])->canAccessPanel($panel));
    }

    public function test_auditoria_es_inmutable_y_usuarios_solo_admin(): void
    {
        $this->actingAs($this->admin());
        app(ConsolaCtl::class)->fpmReload();

        Livewire::test(ListAuditorias::class)->assertOk()->assertCanSeeTableRecords(Auditoria::all());
        Livewire::test(ListUsuarios::class)->assertOk();

        try {
            Auditoria::first()->update(['resultado' => 'error']);
            $this->fail('La auditoría no debe poder modificarse');
        } catch (\LogicException) {
            $this->assertSame('ok', Auditoria::first()->resultado);
        }

        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx', 'rol' => User::ROL_LECTURA]));
        $this->get('/usuarios')->assertForbidden();
    }

    public function test_comando_para_crear_admin(): void
    {
        $this->artisan('consola:usuario', ['email' => 'nuevo@gbpo.com.mx', '--rol' => 'admin'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'unaClaveSegura123')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'nuevo@gbpo.com.mx')->first()->esAdmin());

        $this->artisan('consola:usuario', ['email' => 'x@gmail.com'])->assertFailed();
    }
}
