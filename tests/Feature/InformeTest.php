<?php

namespace Tests\Feature;

use App\Filament\Resources\Destinatarios\Pages\ManageDestinatarios;
use App\Filament\Resources\Informes\Pages\ListInformes;
use App\Mail\AvisoIncidente;
use App\Mail\InformeDiario;
use App\Models\Aplicacion;
use App\Models\Destinatario;
use App\Models\Incidente;
use App\Models\Informe;
use App\Models\Respaldo;
use App\Models\User;
use App\Servicios\Informes\AnalizadorDependencias;
use App\Servicios\Informes\GestorIncidentes;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class InformeTest extends TestCase
{
    use RefreshDatabase;

    private const AUDIT = '{"advisories":{"guzzlehttp/guzzle":[{"title":"Fuga de cabeceras","cve":"CVE-2099-0001","severity":"high","affectedVersions":"<7.9.2"}],"symfony/yaml":{"x":{"title":"Parser lento","cve":null,"severity":"medium"}}}}';

    private const OUTDATED = '{"installed":[{"name":"laravel/framework","version":"v8.83.29","latest":"v13.40.0","latest-status":"update-possible"},{"name":"vlucas/phpdotenv","version":"5.5.0","latest":"5.6.2","latest-status":"semver-safe-update"},{"name":"x/y","version":"1.0","latest":"1.0","latest-status":"up-to-date"}]}';

    private string $apps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        Aplicacion::where('es_pruebas', true)->update(['respaldo_activo' => false]);
        config(['database.connections.lectura.port' => '1']);
        Mail::fake();
        Http::fake(['*' => Http::response('ok')]);
        cache()->flush();
    }

    /** composer simulado: pca-gam tiene vulnerabilidades; el resto limpio */
    private function fingirComposer(): void
    {
        Process::fake(function (PendingProcess $p) {
            $c = $p->command;
            if (str_ends_with((string) $c[0], 'composer')) {
                $esPca = str_ends_with((string) $p->path, '/pca-gam');

                return match ($c[1]) {
                    'audit' => Process::result($esPca ? self::AUDIT : '{"advisories":[]}', '', $esPca ? 1 : 0),
                    'outdated' => Process::result("Aviso de composer\n".($esPca ? self::OUTDATED : '{"installed":[]}')),
                    default => Process::result('', '', 1),
                };
            }

            return Process::result("ActiveState=active\nSubState=running\nMainPID=10\nNRestarts=0");
        });
    }

    public function test_analiza_vulnerabilidades_y_librerias_sin_plugins(): void
    {
        $this->fingirComposer();
        $dir = sys_get_temp_dir().'/consola-prueba-'.uniqid();
        @mkdir($dir.'/pca-gam/vendor', 0775, true);
        file_put_contents($dir.'/pca-gam/composer.lock', '{}');
        $app = new class(['carpeta' => 'pca-gam', 'nombre' => 'pca-gam']) extends Aplicacion
        {
            public static string $base = '';

            public function ruta(): string
            {
                return self::$base.'/'.$this->carpeta;
            }
        };
        $app::$base = $dir;

        $an = app(AnalizadorDependencias::class);
        $v = $an->vulnerabilidades($app);
        $this->assertTrue($v['disponible']);
        $this->assertSame('rojo', $v['semaforo']);
        $this->assertSame(2, $v['total']);
        $this->assertSame(1, $v['altas']);

        $o = $an->desactualizados($app);
        $this->assertSame('amarillo', $o['semaforo']);
        $this->assertSame('laravel/framework', $o['mayores'][0]['paquete']);
        $this->assertSame(1, $o['menores']);

        Process::assertRan(fn (PendingProcess $p) => in_array('audit', $p->command, true)
            && in_array('--no-plugins', $p->command, true) && in_array('--locked', $p->command, true)
            && $p->path === $dir.'/pca-gam');

        // Sin composer.lock: sin datos, no rompe
        $otra = clone $app;
        $otra->carpeta = 'no-existe';
        $this->assertFalse($an->vulnerabilidades($otra)['disponible']);

        array_map('unlink', glob($dir.'/pca-gam/*.*'));
    }

    public function test_genera_y_envia_segun_nivel(): void
    {
        $this->fingirComposer();
        $this->artisan('consola:recolectar');
        Respaldo::create(['base_datos' => 'GamTask', 'resultado' => 'ok', 'tamano_bytes' => 1048576, 'created_at' => now()->subHours(5)]);
        Respaldo::create(['base_datos' => 'akoc-control', 'resultado' => 'error', 'created_at' => now()->subHours(5)]);

        Destinatario::create(['nombre' => 'Todo', 'correo' => 'todo@gbpo.com.mx', 'nivel_minimo' => 'todo']);
        Destinatario::create(['nombre' => 'Rojo', 'correo' => 'rojo@gbpo.com.mx', 'nivel_minimo' => 'rojo']);
        Destinatario::create(['nombre' => 'Inactivo', 'correo' => 'no@gbpo.com.mx', 'activo' => false]);

        $this->artisan('consola:informe-diario')->assertSuccessful();

        $informe = Informe::sole();
        $this->assertSame('rojo', $informe->semaforo); // akoc sin respaldo correcto
        $this->assertTrue($informe->completo);
        $akoc = collect($informe->datos['apps'])->firstWhere('nombre', 'akoc-control');
        $this->assertSame('rojo', $akoc['respaldo']['semaforo']);
        $gamtask = collect($informe->datos['apps'])->firstWhere('nombre', 'GamTask');
        $this->assertSame('verde', $gamtask['respaldo']['semaforo']);
        $this->assertSame('rojo', $informe->datos['recomendaciones'][0]['nivel']); // lo rojo primero
        $this->assertStringContainsString('Semáforo por aplicación', $informe->html);
        $this->assertStringContainsString('akoc-control', $informe->html);
        $this->assertStringContainsString('rojo', $informe->asunto());

        $this->assertEqualsCanonicalizing(['todo@gbpo.com.mx', 'rojo@gbpo.com.mx'], $informe->enviado_a);
        Mail::assertSent(InformeDiario::class, 2);
        Mail::assertSent(InformeDiario::class, fn (InformeDiario $m) => $m->hasTo('rojo@gbpo.com.mx') && str_contains($m->envelope()->subject, 'Informe diario'));
        Mail::assertNotSent(InformeDiario::class, fn (InformeDiario $m) => $m->hasTo('no@gbpo.com.mx'));
    }

    public function test_nivel_del_destinatario(): void
    {
        $d = new Destinatario(['activo' => true, 'nivel_minimo' => 'amarillo']);
        $this->assertFalse($d->recibeInforme('verde'));
        $this->assertTrue($d->recibeInforme('amarillo'));
        $this->assertTrue($d->recibeInforme('rojo'));

        $d->nivel_minimo = 'rojo';
        $this->assertFalse($d->recibeInforme('amarillo'));

        $d->nivel_minimo = 'todo';
        $d->activo = false;
        $this->assertFalse($d->recibeInforme('rojo'));
    }

    public function test_aviso_inmediato_tras_confirmar_y_al_recuperarse(): void
    {
        Destinatario::create(['nombre' => 'Guardia', 'correo' => 'guardia@gbpo.com.mx']);
        Destinatario::create(['nombre' => 'Solo informe', 'correo' => 'informe@gbpo.com.mx', 'avisos_inmediatos' => false]);
        $app = Aplicacion::where('carpeta', 'GamTask')->first();
        $gestor = app(GestorIncidentes::class);
        $alertas = [['nivel' => 'rojo', 'mensaje' => 'El servicio está detenido.'], ['nivel' => 'amarillo', 'mensaje' => 'otra']];

        // Primera lectura en rojo: abre incidente, todavía no avisa
        $gestor->procesar($app, 'rojo', $alertas);
        $this->assertSame(1, Incidente::abiertos()->count());
        Mail::assertNothingSent();

        // Dos minutos después sigue en rojo: avisa una sola vez
        $this->travel(3)->minutes();
        $gestor->procesar($app, 'rojo', $alertas);
        $gestor->procesar($app, 'rojo', $alertas);
        Mail::assertSent(AvisoIncidente::class, 1);
        Mail::assertSent(AvisoIncidente::class, fn (AvisoIncidente $m) => $m->hasTo('guardia@gbpo.com.mx')
            && str_contains($m->envelope()->subject, 'EN FALLA: GamTask')
            && str_contains($m->render(), 'El servicio está detenido.'));
        $this->assertSame(['El servicio está detenido.'], Incidente::first()->motivos);

        // Se recupera: cierra y avisa
        $this->travel(5)->minutes();
        $gestor->procesar($app, 'amarillo', []);
        $incidente = Incidente::sole();
        $this->assertNotNull($incidente->fin);
        $this->assertSame(8, $incidente->duracionMinutos());
        Mail::assertSent(AvisoIncidente::class, 2);
        Mail::assertSent(AvisoIncidente::class, fn (AvisoIncidente $m) => str_contains($m->envelope()->subject, 'Recuperada: GamTask'));
        Mail::assertNotSent(AvisoIncidente::class, fn (AvisoIncidente $m) => $m->hasTo('informe@gbpo.com.mx'));
    }

    public function test_falla_breve_no_avisa_y_pruebas_se_ignoran(): void
    {
        Destinatario::create(['nombre' => 'Guardia', 'correo' => 'guardia@gbpo.com.mx']);
        $gestor = app(GestorIncidentes::class);
        $app = Aplicacion::where('carpeta', 'GamTask')->first();

        // Rojo un minuto y se recupera: queda en el historial pero sin correos
        $gestor->procesar($app, 'rojo', []);
        $this->travel(1)->minutes();
        $gestor->procesar($app, 'verde', []);
        $this->assertNotNull(Incidente::sole()->fin);

        $gestor->procesar(Aplicacion::where('carpeta', 'GamTask-pruebas')->first(), 'rojo', []);
        $this->assertSame(1, Incidente::count());

        // Servidor (sin app)
        $gestor->procesar(null, 'rojo', [['nivel' => 'rojo', 'mensaje' => 'MariaDB está detenido.']]);
        $this->assertSame('Servidor DebWebServ', Incidente::abiertos()->sole()->nombre());

        Mail::assertNothingSent();
    }

    public function test_el_recolector_abre_incidentes_aunque_falle_el_correo(): void
    {
        Process::fake(['*' => Process::result("ActiveState=failed\nSubState=failed")]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        Destinatario::create(['nombre' => 'Guardia', 'correo' => 'guardia@gbpo.com.mx']);

        $this->artisan('consola:recolectar', ['--app' => 'GamTask'])->assertSuccessful();
        $this->travel(3)->minutes();
        $this->artisan('consola:recolectar', ['--app' => 'GamTask'])->assertSuccessful();

        $this->assertNotNull(Incidente::sole()->avisado_en);
    }

    public function test_pantallas_avisar_e_informes(): void
    {
        $this->fingirComposer();
        $admin = User::factory()->create(['email' => 'admin@gbpo.com.mx', 'rol' => User::ROL_ADMIN]);
        $this->actingAs($admin);

        Livewire::test(ManageDestinatarios::class)
            ->callAction('create', data: ['nombre' => 'Externo', 'correo' => 'alguien@gmail.com', 'nivel_minimo' => 'todo'])
            ->assertHasActionErrors(['correo']);
        Livewire::test(ManageDestinatarios::class)
            ->callAction('create', data: ['nombre' => 'Sistemas', 'correo' => 'Sistemas@GBPO.com.mx', 'nivel_minimo' => 'amarillo'])
            ->assertHasNoActionErrors();
        $this->assertSame('sistemas@gbpo.com.mx', Destinatario::sole()->correo);

        Livewire::test(ListInformes::class)
            ->assertOk()
            ->callAction('prueba', data: ['correo' => 'admin@gbpo.com.mx'])
            ->assertHasNoActionErrors();

        $informe = Informe::sole();
        $this->assertFalse($informe->completo); // la prueba genera un informe rápido si no había
        Mail::assertSent(InformeDiario::class, fn (InformeDiario $m) => $m->hasTo('admin@gbpo.com.mx') && str_starts_with($m->envelope()->subject, '[PRUEBA]'));
        Process::assertNotRan(fn (PendingProcess $p) => str_ends_with((string) $p->command[0], 'composer'));

        $this->get('/informes/'.$informe->id)->assertOk()->assertSee('sandbox', false);

        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx']));
        $this->get('/avisar')->assertForbidden();
        Livewire::test(ListInformes::class)->assertOk()->assertActionHidden('prueba')->assertActionHidden('generar');
    }

    public function test_esquema_de_correo_tolerante(): void
    {
        // MAIL_SCHEME=tls no es válido para Symfony Mailer: la config lo ignora en vez de romper el envío
        $this->assertStringContainsString("['smtp', 'smtps']", file_get_contents(config_path('mail.php')));
    }
}
