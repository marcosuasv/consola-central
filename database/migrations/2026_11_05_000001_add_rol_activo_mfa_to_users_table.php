<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles (admin | lectura), usuario activo y verificación en dos pasos (Paso 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 10)->default('lectura')->after('email');
            $table->boolean('activo')->default(true)->after('rol');
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->timestamp('ultimo_acceso')->nullable();
        });

        // Los usuarios que ya existían (los administradores del servidor) quedan como admin.
        DB::table('users')->update(['rol' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'activo', 'app_authentication_secret', 'app_authentication_recovery_codes', 'ultimo_acceso']);
        });
    }
};
