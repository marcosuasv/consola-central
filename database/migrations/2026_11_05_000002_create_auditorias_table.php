<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de acciones privilegiadas (consola-ctl). Solo se inserta; nunca se edita ni se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aplicacion_id')->nullable()->constrained('aplicaciones')->nullOnDelete();
            $table->string('accion', 60)->comment('Ej. servicio.restart, fpm.reload, cola.reiniciar');
            $table->string('objetivo', 120)->nullable()->comment('Unidad o carpeta afectada');
            $table->json('parametros')->nullable();
            $table->string('resultado', 10)->comment('ok | error');
            $table->integer('codigo_salida')->nullable();
            $table->text('salida')->nullable()->comment('Recortada a 5 KB');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
