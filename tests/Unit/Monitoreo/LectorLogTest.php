<?php

namespace Tests\Unit\Monitoreo;

use App\Servicios\Monitoreo\LectorLog;
use App\Servicios\Monitoreo\Systemd;
use Tests\TestCase;

class LectorLogTest extends TestCase
{
    public function test_lee_solo_el_final_y_respeta_rutas(): void
    {
        $ruta = storage_path('framework/testing/prueba.log');
        @mkdir(dirname($ruta), 0775, true);
        file_put_contents($ruta, implode("\n", array_map(fn ($i) => "linea {$i}", range(1, 1000)))."\n");

        $lector = new LectorLog;
        $this->assertSame(['linea 998', 'linea 999', 'linea 1000'], $lector->cola($ruta, 3));

        config(['consola.limites.bytes_log' => 30]); // solo el final del archivo
        $this->assertSame('linea 1000', last($lector->cola($ruta, 100)));
        $this->assertLessThan(5, count($lector->cola($ruta, 100)));

        $this->assertNull($lector->cola('/etc/passwd'));
        $this->assertNull($lector->cola('/var/www/html/app/../../../etc/passwd'));
        $this->assertNull($lector->cola('/var/www/html/GamTask/.env'));

        unlink($ruta);
    }

    public function test_agrupa_entradas_y_filtra_por_nivel(): void
    {
        $lineas = [
            '[2026-11-03 10:00:00] production.INFO: arrancó',
            '[2026-11-03 10:01:00] production.ERROR: falló algo {"exception":"x"}',
            '#0 /var/www/html/app/Foo.php(10): bar()',
            '#1 {main}',
            '[2026-11-03 10:02:00] production.WARNING: lento',
        ];

        $lector = new LectorLog;
        $todas = $lector->entradasLaravel($lineas);
        $this->assertCount(3, $todas);
        $this->assertStringContainsString('#1 {main}', $todas[1]['texto']);

        $this->assertCount(1, $lector->entradasLaravel($lineas, 'ERROR'));
        $this->assertCount(2, $lector->entradasLaravel($lineas, 'WARNING'));
        $this->assertCount(1, $lector->entradasLaravel($lineas, null, 'lento'));
    }

    public function test_oculta_secretos(): void
    {
        $texto = LectorLog::ocultarSecretos('password=Secreta123 token: abcdefghijkl123 APP_KEY=base64:QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo= mysql://root:clave@localhost');

        $this->assertStringNotContainsString('Secreta123', $texto);
        $this->assertStringNotContainsString('abcdefghijkl123', $texto);
        $this->assertStringNotContainsString('QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo', $texto);
        $this->assertStringNotContainsString('clave@', $texto);
    }

    public function test_systemd_solo_acepta_unidades_permitidas(): void
    {
        $this->assertTrue(Systemd::unidadPermitida('app-gamtask'));
        $this->assertTrue(Systemd::unidadPermitida('app-gamtask-scheduler.timer'));
        $this->assertTrue(Systemd::unidadPermitida('mariadb.service'));
        $this->assertFalse(Systemd::unidadPermitida('ssh'));
        $this->assertFalse(Systemd::unidadPermitida('app-gamtask;id'));
        $this->assertFalse(Systemd::unidadPermitida('../app-x'));

        $this->expectException(\InvalidArgumentException::class);
        (new Systemd)->propiedades('sshd.service');
    }
}
