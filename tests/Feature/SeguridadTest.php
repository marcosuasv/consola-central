<?php

namespace Tests\Feature;

use App\Filament\Pages\Seguridad;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Models\User;
use App\Servicios\Control\ConsolaCtl;
use App\Servicios\Monitoreo\LectorSistema;
use App\Servicios\Seguridad\AnalizadorNginx;
use App\Servicios\Seguridad\Fail2ban;
use App\Servicios\Seguridad\Ip;
use App\Servicios\Seguridad\RegistroConsolaCtl;
use Database\Seeders\AplicacionesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private const STATUS = "Status\n|- Number of jail:\t2\n`- Jail list:\tsshd, nginx-4xx";

    private const SSHD = "Status for the jail: sshd\n|- Filter\n|  |- Currently failed:\t3\n|  |- Total failed:\t40\n|  `- File list:\t/var/log/auth.log\n`- Actions\n   |- Currently banned:\t2\n   |- Total banned:\t9\n   `- Banned IP list:\t203.0.113.7 198.51.100.20";

    private const NGINX4XX = "Status for the jail: nginx-4xx\n|- Filter\n|  |- Currently failed:\t0\n`- Actions\n   |- Currently banned:\t0\n   |- Total banned:\t1\n   `- Banned IP list:\t";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AplicacionesSeeder::class);
        $this->dir = storage_path('framework/testing/seguridad');
        @mkdir($this->dir, 0775, true);
        cache()->flush();

        Process::fake(function (PendingProcess $p) {
            $c = $p->command;
            if ($c[0] !== 'sudo') {
                return Process::result('ActiveState=active');
            }
            $args = array_slice($c, 3);

            return match (true) {
                $args === ['f2b', 'status'] => Process::result(self::STATUS),
                $args === ['f2b', 'status', 'sshd'] => Process::result(self::SSHD),
                $args === ['f2b', 'status', 'nginx-4xx'] => Process::result(self::NGINX4XX),
                $args[0] === 'f2b' && $args[1] === 'unban' => Process::result('1'),
                default => Process::result('', 'ERROR', 2),
            };
        });
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@gbpo.com.mx', 'rol' => User::ROL_ADMIN]);
        $u->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $u;
    }

    public function test_ip_publica_o_privada(): void
    {
        foreach (['8.8.8.8', '203.0.113.7', '2001:4860:4860::8888'] as $ip) {
            $this->assertTrue(Ip::esPublica($ip), $ip);
        }
        foreach (['10.2.0.5', '172.16.4.1', '172.31.255.1', '192.168.1.10', '127.0.0.1', '::1', 'basura', null] as $ip) {
            $this->assertFalse(Ip::esPublica($ip), (string) $ip);
        }
    }

    public function test_estado_de_fail2ban_sin_auditar_consultas(): void
    {
        $estado = app(Fail2ban::class)->estado();

        $this->assertNull($estado['error']);
        $this->assertSame(['sshd', 'nginx-4xx'], array_keys($estado['reglas']));
        $this->assertSame(2, $estado['reglas']['sshd']['actuales']);
        $this->assertSame(9, $estado['reglas']['sshd']['total']);
        $this->assertSame(['203.0.113.7', '198.51.100.20'], $estado['reglas']['sshd']['ips']);
        $this->assertSame([], $estado['reglas']['nginx-4xx']['ips']);
        $this->assertSame(0, Auditoria::count()); // las consultas exitosas no llenan la auditoría
    }

    public function test_historial_de_fail2ban(): void
    {
        $hoy = now()->format('Y-m-d');
        $ayer = now()->subDay()->format('Y-m-d');
        $viejo = now()->subDays(10)->format('Y-m-d');
        file_put_contents($this->dir.'/fail2ban.log', implode("\n", [
            "{$viejo} 10:00:00,1 fail2ban.actions [1]: NOTICE  [sshd] Ban 192.0.2.1",
            "{$ayer} 10:00:00,1 fail2ban.filter  [1]: INFO    [sshd] Found 203.0.113.7 - {$ayer} 10:00:00",
            "{$ayer} 10:00:05,1 fail2ban.actions [1]: NOTICE  [sshd] Ban 203.0.113.7",
            "{$hoy} 09:00:00,1 fail2ban.actions [1]: NOTICE  [nginx-4xx] Ban 203.0.113.7",
            "{$hoy} 09:30:00,1 fail2ban.actions [1]: NOTICE  [sshd] Unban 203.0.113.7",
            "{$hoy} 09:40:00,1 fail2ban.actions [1]: NOTICE  [sshd] Ban 198.51.100.20",
        ])."\n");

        $r = Fail2ban::resumir(app(Fail2ban::class)->historial(7, $this->dir.'/fail2ban.log'));

        $this->assertSame(3, $r['bloqueos']);
        $this->assertSame(1, $r['intentos']);
        $this->assertSame(['sshd' => 2, 'nginx-4xx' => 1], $r['por_regla']);
        $this->assertSame('203.0.113.7', $r['ips'][0]['ip']);
        $this->assertSame(2, $r['ips'][0]['bloqueos']);
        $this->assertSame(2, $r['por_dia'][$hoy]);
        $this->assertSame('Unban', $r['recientes'][1]['tipo']);
    }

    public function test_visitas_publicas_desde_nginx(): void
    {
        $f = fn ($minutos) => now()->subMinutes($minutos)->format('d/M/Y:H:i:s O');
        $viejo = now()->subDays(2)->format('d/M/Y:H:i:s O');
        file_put_contents($this->dir.'/app.access.log', implode("\n", [
            "203.0.113.7 - - [{$viejo}] \"GET / HTTP/1.1\" 200 10 \"-\" \"curl\"",
            "10.2.0.50 - - [{$f(30)}] \"GET /tablero HTTP/1.1\" 200 10 \"-\" \"Mozilla\"",
            "203.0.113.7 - - [{$f(20)}] \"GET /.env HTTP/1.1\" 404 10 \"-\" \"zgrab/0.x\"",
            "203.0.113.7 - - [{$f(19)}] \"POST /wp-login.php HTTP/1.1\" 404 10 \"-\" \"zgrab/0.x\"",
            "198.51.100.20 - - [{$f(10)}] \"GET /facturas?id=3 HTTP/1.1\" 500 10 \"-\" \"Mozilla/5.0\"",
            "198.51.100.20 - - [{$f(5)}] \"GET /facturas?id=4 HTTP/1.1\" 200 10 \"-\" \"Mozilla/5.0\"",
            'línea que no es de nginx',
        ])."\n");

        $d = app(AnalizadorNginx::class)->analizarArchivo($this->dir.'/app.access.log');

        $this->assertTrue($d['disponible']);
        $this->assertSame(5, $d['total']);          // la de hace 2 días queda fuera
        $this->assertSame(4, $d['publicas']);       // la de 10.x es interna
        $this->assertSame(2, $d['ips_publicas']);
        $this->assertSame(2, $d['errores_4xx']);
        $this->assertSame(1, $d['errores_5xx']);
        $this->assertSame(2, $d['sospechosas']);
        $this->assertSame('203.0.113.7', $d['top_ips'][0]['ip']); // la más sospechosa primero
        $this->assertTrue($d['top_rutas'][0]['sospechosa']);
        $this->assertSame('/facturas', collect($d['top_rutas'])->firstWhere('sospechosa', false)['ruta']);
        $this->assertSame(4, array_sum($d['por_hora']));
    }

    public function test_registro_de_consola_ctl_y_alerta_del_servidor(): void
    {
        $ruta = $this->dir.'/consola-ctl.log';
        $ahora = now()->format('Y-m-d H:i:s');
        file_put_contents($ruta, implode("\n", [
            now()->subDays(3)->format('Y-m-d H:i:s').' usuario=consola RECHAZADO: unidad no permitida: ssh',
            "{$ahora} usuario=consola servicio restart app-gamtask",
            "{$ahora} usuario=consola RECHAZADO: unidad no permitida: app-gamtask;id",
        ])."\n");

        $registro = app(RegistroConsolaCtl::class);
        $entradas = $registro->entradas(10, $ruta);
        $this->assertCount(3, $entradas);
        $this->assertTrue($entradas[0]['rechazado']);
        $this->assertSame(1, $registro->rechazosDesde(now()->toImmutable()->subDay(), $ruta));

        // El semáforo del servidor avisa los rechazos
        $this->mock(RegistroConsolaCtl::class, fn ($m) => $m->shouldReceive('rechazosDesde')->andReturn(2));
        $evaluacion = app(LectorSistema::class)->evaluar([
            'servicios' => [], 'montaje_facturas' => true, 'disco_total' => null, 'ram_total' => null, 'cert_dias' => null, 'cpus' => null, 'carga_5' => null,
        ]);
        $this->assertSame('amarillo', $evaluacion['semaforo']);
        $this->assertStringContainsString('consola-ctl rechazó 2 acciones', $evaluacion['alertas'][0]['mensaje']);
    }

    public function test_desbloquear_ip(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Seguridad::class)
            ->assertSee('203.0.113.7')
            ->callAction('desbloquear', arguments: ['regla' => 'sshd', 'ip' => '203.0.113.7'])
            ->assertNotified('203.0.113.7 desbloqueada');

        Process::assertRan(fn (PendingProcess $p) => $p->command === ['sudo', '-n', ConsolaCtl::BINARIO, 'f2b', 'unban', 'sshd', '203.0.113.7']);
        $registro = Auditoria::sole();
        $this->assertSame('f2b.unban', $registro->accion);
        $this->assertSame('sshd 203.0.113.7', $registro->objetivo);
    }

    public function test_desbloquear_rechaza_datos_invalidos(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Seguridad::class)
            ->callAction('desbloquear', arguments: ['regla' => 'sshd; reboot', 'ip' => '203.0.113.7'])
            ->assertNotified('Datos inválidos')
            ->callAction('desbloquear', arguments: ['regla' => 'sshd', 'ip' => '999.1.1.1'])
            ->assertNotified('Datos inválidos');

        Process::assertNotRan(fn (PendingProcess $p) => in_array('unban', $p->command, true));
        $this->assertSame(0, Auditoria::count());
    }

    public function test_lectura_ve_la_pagina_pero_no_desbloquea(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'lector@gbpo.com.mx']));

        $pagina = Livewire::test(Seguridad::class)->assertOk()->assertActionHidden('desbloquear');

        foreach (array_keys(Seguridad::PESTANAS) as $pestana) {
            $pagina->call('cambiarPestana', $pestana)->assertOk()->assertSet('pestana', $pestana);
        }

        $pagina->call('cambiarPestana', 'visitas')
            ->call('elegirApp', Aplicacion::where('carpeta', 'akoc-control')->value('id'))
            ->assertOk()
            ->call('elegirApp', 99999)
            ->assertSet('app', null);
    }

    public function test_fail2ban_caido_muestra_aviso(): void
    {
        Process::fake(['*' => Process::result('', 'ERROR: fail2ban no responde', 1)]);
        $this->actingAs($this->admin());

        Livewire::test(Seguridad::class)->assertOk()->assertSee('No se pudo consultar Fail2ban');
        $this->assertSame('error', Auditoria::first()->resultado); // los fallos sí se auditan
    }
}
