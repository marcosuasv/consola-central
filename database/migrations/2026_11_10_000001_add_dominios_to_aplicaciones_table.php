<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dominios configurados para cada app (Paso 13, asistente de dominios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aplicaciones', function (Blueprint $table) {
            $table->string('dominios', 500)->nullable()->after('url')->comment('Lista separada por comas, ej. task.gbpo.com.mx');
        });
    }

    public function down(): void
    {
        Schema::table('aplicaciones', function (Blueprint $table) {
            $table->dropColumn('dominios');
        });
    }
};
