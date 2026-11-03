<?php

namespace Tests\Feature;

use App\Filament\Resources\Aplicaciones\Pages\CreateAplicacion;
use App\Filament\Resources\Aplicaciones\Pages\ListAplicaciones;
use App\Models\Aplicacion;
use App\Models\User;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AplicacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'admin@gbpo.com.mx']));
    }

    public function test_el_seeder_carga_las_seis_aplicaciones_y_es_repetible(): void
    {
        $this->seed(AplicacionesSeeder::class);
        $this->seed(AplicacionesSeeder::class);

        $this->assertSame(6, Aplicacion::count());

        $pca = Aplicacion::where('carpeta', 'pca-gam')->firstOrFail();
        $this->assertTrue($pca->publica_internet);
        $this->assertSame('app-pcagam', $pca->servicio);
        $this->assertSame('app-pcagam-scheduler.timer', $pca->timer_scheduler);
        $this->assertSame('http://127.0.0.1:18089/', $pca->url_salud);
        $this->assertSame([8089, 4889], $pca->listaPuertosPublicos());

        $intranet = Aplicacion::where('carpeta', 'intranet_GBPO')->firstOrFail();
        $this->assertTrue($intranet->esFpm());
        $this->assertSame('intranet', $intranet->pool_fpm);
        $this->assertFalse($intranet->sesiones_en_bd);

        $pruebas = Aplicacion::where('es_pruebas', true)->pluck('carpeta')->sort()->values()->all();
        $this->assertSame(['GamTask-pruebas', 'pca-gam-pruebas'], $pruebas);
        $this->assertFalse(Aplicacion::where('carpeta', 'GamTask-pruebas')->value('arranque_automatico'));
    }

    public function test_los_valores_del_seeder_cumplen_las_validaciones(): void
    {
        $this->seed(AplicacionesSeeder::class);

        foreach (Aplicacion::all() as $app) {
            $this->assertMatchesRegularExpression(Aplicacion::REGEX_CARPETA, $app->carpeta);
            $this->assertMatchesRegularExpression(Aplicacion::REGEX_URL_SALUD, $app->url_salud);
            $this->assertMatchesRegularExpression(Aplicacion::REGEX_LOG_NGINX, $app->log_nginx);
            $this->assertMatchesRegularExpression(Aplicacion::REGEX_PUERTOS, $app->puertos_publicos);
            foreach (['servicio', 'servicio_cola'] as $campo) {
                if ($app->{$campo} !== null) {
                    $this->assertMatchesRegularExpression(Aplicacion::REGEX_SERVICIO, $app->{$campo});
                }
            }
            if ($app->timer_scheduler !== null) {
                $this->assertMatchesRegularExpression(Aplicacion::REGEX_TIMER, $app->timer_scheduler);
            }
        }
    }

    public function test_la_lista_muestra_las_aplicaciones(): void
    {
        $this->seed(AplicacionesSeeder::class);

        Livewire::test(ListAplicaciones::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Aplicacion::all());
    }

    public function test_crear_aplicacion_octane(): void
    {
        Livewire::test(CreateAplicacion::class)
            ->fillForm([
                'nombre' => 'Nueva',
                'carpeta' => 'nueva-app',
                'tipo' => Aplicacion::TIPO_OCTANE,
                'servicio' => 'app-nueva',
                'servicio_cola' => 'app-nueva-queue',
                'timer_scheduler' => 'app-nueva-scheduler.timer',
                'puerto_interno' => 18100,
                'puertos_publicos' => '8100',
                'url_salud' => 'http://127.0.0.1:18100/',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('aplicaciones', ['carpeta' => 'nueva-app', 'servicio' => 'app-nueva', 'pool_fpm' => null]);
    }

    public function test_rechaza_valores_peligrosos(): void
    {
        Livewire::test(CreateAplicacion::class)
            ->fillForm([
                'nombre' => 'Mala',
                'carpeta' => '../etc',
                'tipo' => Aplicacion::TIPO_OCTANE,
                'servicio' => 'nginx; rm -rf /',
                'timer_scheduler' => 'app-mala-scheduler',
                'puerto_interno' => 80,
                'puertos_publicos' => '80, 443',
                'url_salud' => 'http://10.2.0.5/',
                'log_nginx' => '/etc/shadow',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'carpeta' => 'regex',
                'servicio' => 'regex',
                'timer_scheduler' => 'regex',
                'puerto_interno',
                'puertos_publicos' => 'regex',
                'url_salud' => 'regex',
                'log_nginx' => 'regex',
            ]);

        $this->assertSame(0, Aplicacion::count());
    }

    public function test_fpm_requiere_pool_y_limpia_campos_octane(): void
    {
        Livewire::test(CreateAplicacion::class)
            ->fillForm(['nombre' => 'Otra FPM', 'carpeta' => 'otra_fpm', 'tipo' => Aplicacion::TIPO_FPM])
            ->call('create')
            ->assertHasFormErrors(['pool_fpm' => 'required']);

        Livewire::test(CreateAplicacion::class)
            ->fillForm(['nombre' => 'Otra FPM', 'carpeta' => 'otra_fpm', 'tipo' => Aplicacion::TIPO_FPM, 'pool_fpm' => 'otra'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('aplicaciones', ['carpeta' => 'otra_fpm', 'pool_fpm' => 'otra', 'servicio' => null, 'puerto_interno' => null]);
    }
}
