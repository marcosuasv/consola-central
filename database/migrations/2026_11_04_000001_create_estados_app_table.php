<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lecturas del recolector por aplicación (una fila por app cada minuto). Paso 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplicacion_id')->constrained('aplicaciones')->cascadeOnDelete();

            // systemd (o PHP-FPM en apps fpm)
            $table->string('servicio_estado', 20)->nullable()->comment('ActiveState');
            $table->string('servicio_subestado', 20)->nullable()->comment('SubState');
            $table->string('cola_estado', 20)->nullable();
            $table->string('timer_estado', 20)->nullable();
            $table->unsignedInteger('pid')->nullable();
            $table->unsignedBigInteger('memoria_bytes')->nullable();
            $table->unsignedBigInteger('cpu_ns')->nullable();
            $table->timestamp('activo_desde')->nullable();
            $table->unsignedInteger('reinicios_systemd')->nullable()->comment('NRestarts acumulado');
            $table->unsignedInteger('reinicios_24h')->nullable();

            // Salud HTTP
            $table->unsignedSmallInteger('http_codigo')->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->string('http_error', 255)->nullable();

            // Uso
            $table->unsignedInteger('usuarios_conectados')->nullable();
            $table->unsignedInteger('sesiones_invitado')->nullable();
            $table->unsignedInteger('jobs_pendientes')->nullable();
            $table->unsignedInteger('jobs_fallidos_24h')->nullable();
            $table->unsignedInteger('errores_log_24h')->nullable();

            // Resultado
            $table->string('semaforo', 10)->comment('verde | amarillo | rojo | gris');
            $table->json('alertas')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->index(['aplicacion_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_app');
    }
};
