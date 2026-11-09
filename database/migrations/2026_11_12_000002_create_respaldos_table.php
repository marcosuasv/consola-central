<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 14: historial de respaldos de bases de datos (los archivos los escribe root vía consola-ctl).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplicacion_id')->nullable()->constrained('aplicaciones')->nullOnDelete();
            $table->string('base_datos', 64);
            $table->string('archivo', 255)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->string('resultado', 10)->comment('ok | error');
            $table->text('mensaje')->nullable();
            $table->string('origen', 10)->default('programado')->comment('programado | manual');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['base_datos', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respaldos');
    }
};
