<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('abogado_coordinaciones')) {
            return;
        }

        Schema::table('abogado_coordinaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('abogado_coordinaciones', 'eleccion_mesa_id')) {
                $table->foreignId('eleccion_mesa_id')
                    ->nullable()
                    ->after('eleccion_id')
                    ->constrained('eleccion_mesas')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('abogado_coordinaciones')) {
            return;
        }

        Schema::table('abogado_coordinaciones', function (Blueprint $table) {
            if (Schema::hasColumn('abogado_coordinaciones', 'eleccion_mesa_id')) {
                $table->dropConstrainedForeignId('eleccion_mesa_id');
            }
        });
    }
};
