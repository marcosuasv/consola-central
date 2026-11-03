<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lecturas del recolector para el servidor completo (una fila por minuto). Paso 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_sistema', function (Blueprint $table) {
            $table->id();
            $table->decimal('carga_1', 6, 2)->nullable();
            $table->decimal('carga_5', 6, 2)->nullable();
            $table->decimal('carga_15', 6, 2)->nullable();
            $table->unsignedSmallInteger('cpus')->nullable();
            $table->unsignedBigInteger('ram_total')->nullable();
            $table->unsignedBigInteger('ram_usada')->nullable();
            $table->unsignedBigInteger('disco_total')->nullable();
            $table->unsignedBigInteger('disco_usado')->nullable();
            $table->json('servicios')->nullable()->comment('{"MariaDB":"active",...}');
            $table->boolean('montaje_facturas')->nullable();
            $table->integer('cert_dias')->nullable();
            $table->string('semaforo', 10);
            $table->json('alertas')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_sistema');
    }
};
