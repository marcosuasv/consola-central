<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 12: destinatarios de correo, informes diarios e incidentes (avisos inmediatos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinatarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('correo', 150)->unique();
            $table->boolean('activo')->default(true);
            $table->string('nivel_minimo', 10)->default('todo')->comment('Informe diario: todo | amarillo | rojo');
            $table->boolean('avisos_inmediatos')->default(true)->comment('Correo al momento cuando algo pasa a rojo');
            $table->timestamps();
        });

        Schema::create('informes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('semaforo', 10);
            $table->unsignedSmallInteger('rojos')->default(0);
            $table->unsignedSmallInteger('amarillos')->default(0);
            $table->json('datos');
            $table->longText('html');
            $table->json('enviado_a')->nullable();
            $table->string('error_envio', 500)->nullable();
            $table->boolean('completo')->default(true)->comment('false = sin análisis de dependencias');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('incidentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplicacion_id')->nullable()->constrained('aplicaciones')->cascadeOnDelete()->comment('null = servidor');
            $table->timestamp('inicio')->index();
            $table->timestamp('fin')->nullable();
            $table->json('motivos')->nullable();
            $table->timestamp('avisado_en')->nullable();
            $table->timestamp('recuperacion_avisada_en')->nullable();
            $table->index(['aplicacion_id', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentes');
        Schema::dropIfExists('informes');
        Schema::dropIfExists('destinatarios');
    }
};
