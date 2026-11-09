<?php

namespace Tests\Feature;

use App\Filament\Pages\AgregarAplicacion;
use App\Filament\Pages\AgregarDominio;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Models\User;
use App\Servicios\Asistente\GeneradorAplicacion;
use App\Servicios\Asistente\GeneradorDominio;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class AsistenteTest extends TestCase
{
    use RefreshDatabase;

    private const APP = [
        'nombre' => 'Inventario', 'carpeta' => 'inventario', 'tipo' => 'octane', 'clave' => 'inventario',
        'usuario' => 'www-data', 'octane_servidor' => 'roadrunner', 'puerto_interno' => 18100,
        'puertos_publicos' => '8100', 'crear_bd' => true, 'base_datos' => 'inventario',
        'usuario_bd' => 'inventario', 'sesiones_en_bd' => true, 'publica' => false, 'pruebas' => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        Process::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@gbpo.com.mx', 'rol' => User::ROL_ADMIN]);
    }

    /** bash -n: el script generado debe tener sintaxis válida */
    private function assertBashValido(string $script): void
    {
        $archivo = tempnam(sys_get_temp_dir(), 'consola');
        file_put_contents($archivo, $script);
        exec('bash -n '.escapeshellarg($archivo).' 2>&1', $salida, $codigo);
        unlink($archivo);
        $this->assertSame(0, $codigo, implode("\n", $salida));
    }

    public function test_genera_app_octane_completa(): void
    {
        $p = app(GeneradorAplicacion::class)->generar(self::APP);

        $this->assertBashValido($p->script);
        $rutas = array_column($p->archivos, 'ruta');
        foreach ([
            '/etc/systemd/system/app-inventario.service',
            '/etc/systemd/system/app-inventario-queue.service',
            '/etc/systemd/system/app-inventario-scheduler.service',
            '/etc/systemd/system/app-inventario-scheduler.timer',
            '/etc/nginx/sites-available/app-inventario',
            '/root/consola-ro-inventario.sql',
        ] as $ruta) {
            $this->assertContains($ruta, $rutas);
        }

        $this->assertStringContainsString('ExecStart=/usr/bin/php artisan octane:start --server=roadrunner --host=127.0.0.1 --port=18100', $p->script);
        $this->assertStringContainsString('Wants=app-inventario-queue.service app-inventario-scheduler.timer', $p->script);
        $this->assertStringContainsString('WorkingDirectory=/var/www/html/inventario', $p->script);
        $this->assertStringContainsString('PartOf=app-inventario.service', $p->script);
        $this->assertStringContainsString('error_page 497', $p->script);
        $this->assertStringContainsString('include snippets/laravel-proxy.conf;', $p->script);
        $this->assertStringContainsString('access_log /var/log/nginx/app-inventario.access.log;', $p->script);
        $this->assertStringContainsString('ufw allow from 10.0.0.0/8 to any port 8100 proto tcp', $p->script);
        $this->assertStringNotContainsString('ufw allow 8100/tcp', $p->script);
        $this->assertStringContainsString('systemctl restart fail2ban', $p->script);
        $this->assertStringContainsString('systemctl enable --now app-inventario.service', $p->script);
        $this->assertStringContainsString("GRANT SELECT (user_id, ip_address, user_agent, last_activity) ON `inventario`.`sessions` TO 'consola_ro'", $p->script);
        // El SQL va en heredoc entre comillas: los acentos graves no se ejecutan en bash
        $this->assertStringContainsString("<<'SQL_CONSOLA'", $p->script);
        $this->assertStringNotContainsString('__CONTRASENA__\'', str_replace("'__CONTRASENA__'", '', $p->script));
    }

    public function test_app_fpm_publica_y_de_pruebas(): void
    {
        $p = app(GeneradorAplicacion::class)->generar([
            ...self::APP, 'carpeta' => 'portal_web', 'clave' => 'portal', 'tipo' => 'fpm', 'pool' => 'portal',
            'puerto_interno' => null, 'puertos_publicos' => '8110', 'publica' => true, 'crear_bd' => false, 'pruebas' => true,
        ]);

        $this->assertBashValido($p->script);
        $this->assertContains('/etc/php/8.2/fpm/pool.d/portal.conf', array_column($p->archivos, 'ruta'));
        $this->assertStringContainsString('fastcgi_pass unix:/run/php/php8.2-fpm-portal.sock;', $p->script);
        $this->assertStringContainsString("ufw allow 8110/tcp comment 'consola: publico'", $p->script);
        $this->assertStringNotContainsString('CREATE DATABASE', $p->script);
        $this->assertStringNotContainsString('app-portal.service', $p->script);
    }

    public function test_rechaza_puertos_ocupados_y_valores_peligrosos(): void
    {
        $gen = app(GeneradorAplicacion::class);

        foreach ([
            ['puerto_interno' => 18070],                       // de GamTask
            ['puertos_publicos' => '8100,443'],               // 443 de GamTask
            ['carpeta' => 'GamTask'],                         // ya registrada
            ['carpeta' => '../etc'],
            ['clave' => 'x; rm -rf /'],
            ['usuario' => 'root$(id)'],
            ['base_datos' => 'bd`; DROP'],
            ['usuario_bd' => "u' OR 1"],
            ['nombre' => "App\nExecStart=/bin/sh"],
        ] as $cambio) {
            try {
                $gen->generar([...self::APP, ...$cambio]);
                $this->fail('Debió rechazarse: '.json_encode($cambio));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_dominio_con_lets_encrypt(): void
    {
        $gamtask = Aplicacion::where('carpeta', 'GamTask')->first();
        $p = app(GeneradorDominio::class)->generar([
            'aplicacion_id' => $gamtask->id, 'dominio' => 'Task.GBPO.com.mx', 'tls' => 'letsencrypt',
            'correo' => 'sistemas@gbpo.com.mx', 'publico' => false,
        ]);

        $this->assertBashValido($p->script);
        $this->assertStringContainsString('server_name task.gbpo.com.mx;', $p->script);
        $this->assertStringContainsString('certbot certonly --webroot', $p->script);
        $this->assertStringContainsString('/etc/letsencrypt/live/task.gbpo.com.mx/fullchain.pem', $p->script);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:18070;', $p->script);
        $this->assertStringContainsString('access_log /var/log/nginx/app-gamtask.access.log;', $p->script);
        $this->assertStringContainsString('return 301 https://$host$request_uri;', $p->script);
        $this->assertStringContainsString('ufw allow from 192.168.0.0/16 to any port 443 proto tcp', $p->script);
        $this->assertStringContainsString('DNS interno: registro A task.gbpo.com.mx → 10.2.0.170', $p->script);
    }

    public function test_dominio_con_certificado_propio_y_publico(): void
    {
        $pca = Aplicacion::where('carpeta', 'pca-gam')->first();
        $p = app(GeneradorDominio::class)->generar([
            'aplicacion_id' => $pca->id, 'dominio' => 'facturas.gbpo.com.mx', 'tls' => 'archivo',
            'certificado' => '/etc/ssl/gbpo/fullchain.pem', 'certificado_llave' => '/etc/ssl/gbpo/privkey.pem',
            'publico' => true, 'redirigir_http' => false, 'hsts' => true,
        ]);

        $this->assertBashValido($p->script);
        $this->assertStringContainsString('ssl_certificate     /etc/ssl/gbpo/fullchain.pem;', $p->script);
        $this->assertStringContainsString('Strict-Transport-Security', $p->script);
        $this->assertStringContainsString("ufw allow 443/tcp comment 'consola: publico'", $p->script);
        $this->assertStringNotContainsString('listen 80;', $p->script);
        $this->assertStringNotContainsString('certbot', $p->script);
    }

    public function test_dominio_rechaza_datos_invalidos(): void
    {
        $gen = app(GeneradorDominio::class);
        $id = Aplicacion::where('carpeta', 'GamTask')->value('id');
        Aplicacion::where('carpeta', 'akoc-control')->update(['dominios' => 'akoc.gbpo.com.mx']);

        foreach ([
            ['dominio' => 'https://task.gbpo.com.mx/'],
            ['dominio' => 'task.gbpo.com.mx; reboot'],
            ['dominio' => 'akoc.gbpo.com.mx'],                         // ya es de otra app
            ['tls' => 'archivo', 'certificado' => '/root/../etc/shadow', 'certificado_llave' => '/etc/x.key'],
            ['tls' => 'letsencrypt', 'correo' => 'no-es-correo'],
            ['aplicacion_id' => 99999],
        ] as $cambio) {
            try {
                $gen->generar(['aplicacion_id' => $id, 'dominio' => 'task.gbpo.com.mx', 'tls' => 'local', ...$cambio]);
                $this->fail('Debió rechazarse: '.json_encode($cambio));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_asistente_de_app_en_la_consola(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AgregarAplicacion::class)
            ->assertOk()
            ->assertSet('data.puerto_interno', 18100)
            ->fillForm(self::APP)
            ->call('generar')
            ->assertHasNoFormErrors()
            ->assertNotified('Script generado')
            ->assertSee('nueva-app-inventario.sh')
            ->callAction('descargar')
            ->assertFileDownloaded('nueva-app-inventario.sh')
            ->callAction('crearFicha');

        $app = Aplicacion::where('carpeta', 'inventario')->sole();
        $this->assertSame('app-inventario', $app->servicio);
        $this->assertSame('app-inventario-scheduler.timer', $app->timer_scheduler);
        $this->assertSame('http://127.0.0.1:18100/', $app->url_salud);
        $this->assertSame('/var/log/nginx/app-inventario.access.log', $app->log_nginx);
        $this->assertTrue(Auditoria::where('accion', 'asistente.generar')->exists());
        $this->assertTrue(Auditoria::where('accion', 'asistente.registrar_app')->exists());
        Process::assertNothingRan(); // el asistente nunca ejecuta comandos
    }

    public function test_asistente_de_dominio_en_la_consola(): void
    {
        $this->actingAs($this->admin());
        $gamtask = Aplicacion::where('carpeta', 'GamTask')->first();

        Livewire::withQueryParams(['app' => $gamtask->id])
            ->test(AgregarDominio::class)
            ->assertSet('data.aplicacion_id', $gamtask->id)
            ->fillForm(['dominio' => 'task.gbpo.com.mx', 'tls' => 'local', 'publico' => false])
            ->call('generar')
            ->assertHasNoFormErrors()
            ->assertSee('dominio-task.gbpo.com.mx.sh')
            ->callAction('crearFicha');

        $gamtask->refresh();
        $this->assertSame(['task.gbpo.com.mx'], $gamtask->listaDominios());
        $this->assertSame('https://task.gbpo.com.mx/', $gamtask->url);
        Process::assertNothingRan();
    }

    public function test_solo_admin_usa_los_asistentes(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx']));

        $this->get(AgregarAplicacion::getUrl())->assertForbidden();
        $this->get(AgregarDominio::getUrl())->assertForbidden();
    }
}
