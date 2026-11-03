<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de las aplicaciones Laravel que vigila la Consola Central (Paso 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('carpeta', 100)->unique()->comment('Carpeta dentro de /var/www/html');
            $table->string('tipo', 10)->default('octane')->comment('octane | fpm');

            // Unidades de systemd (solo apps Octane) y pool de PHP-FPM (solo apps FPM)
            $table->string('servicio', 100)->nullable()->comment('Ej. app-gamtask');
            $table->string('servicio_cola', 100)->nullable()->comment('Ej. app-gamtask-queue');
            $table->string('timer_scheduler', 100)->nullable()->comment('Ej. app-gamtask-scheduler.timer');
            $table->string('pool_fpm', 50)->nullable()->comment('Ej. intranet');

            // Red
            $table->unsignedSmallInteger('puerto_interno')->nullable();
            $table->string('puertos_publicos', 100)->nullable()->comment('Lista separada por comas');
            $table->string('url')->nullable()->comment('URL para abrir la app');
            $table->string('url_salud')->nullable()->comment('URL local para la prueba de salud');

            // Base de datos
            $table->string('base_datos', 64)->nullable();
            $table->string('usuario_bd', 80)->nullable();
            $table->boolean('sesiones_en_bd')->default(true);

            // Logs
            $table->string('log_nginx')->nullable()->comment('Ruta del access.log de Nginx');

            // Comportamiento
            $table->boolean('es_pruebas')->default(false);
            $table->boolean('arranque_automatico')->default(true);
            $table->boolean('publica_internet')->default(false);
            $table->boolean('monitorear')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->text('notas')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones');
    }
};
