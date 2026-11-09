<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 14: qué apps se respaldan y cuántos días se conservan sus respaldos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aplicaciones', function (Blueprint $table) {
            $table->boolean('respaldo_activo')->default(true)->after('monitorear');
            $table->unsignedSmallInteger('respaldo_dias')->default(14)->after('respaldo_activo');
        });

        // Los entornos de pruebas no se respaldan por defecto
        DB::table('aplicaciones')->where('es_pruebas', true)->update(['respaldo_activo' => false]);
    }

    public function down(): void
    {
        Schema::table('aplicaciones', function (Blueprint $table) {
            $table->dropColumn(['respaldo_activo', 'respaldo_dias']);
        });
    }
};
