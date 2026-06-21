<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elecciones', function (Blueprint $table) {
            $table->boolean('habilitar_afluencia')->default(true)->after('meta_testigos_pct');
            $table->boolean('habilitar_datos_e14')->default(true)->after('habilitar_afluencia');
            $table->boolean('habilitar_informacion_final')->default(true)->after('habilitar_datos_e14');
            $table->boolean('habilitar_foto_e14')->default(true)->after('habilitar_informacion_final');
        });
    }

    public function down(): void
    {
        Schema::table('elecciones', function (Blueprint $table) {
            $table->dropColumn([
                'habilitar_afluencia',
                'habilitar_datos_e14',
                'habilitar_informacion_final',
                'habilitar_foto_e14',
            ]);
        });
    }
};
