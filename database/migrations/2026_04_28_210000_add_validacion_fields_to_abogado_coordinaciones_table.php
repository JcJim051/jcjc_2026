<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('abogado_coordinaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('abogado_coordinaciones', 'validacion_estado')) {
                $table->string('validacion_estado')->default('asignado')->after('released_at');
            }
            if (!Schema::hasColumn('abogado_coordinaciones', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('validacion_estado');
            }
        });
    }

    public function down()
    {
        Schema::table('abogado_coordinaciones', function (Blueprint $table) {
            if (Schema::hasColumn('abogado_coordinaciones', 'observaciones')) {
                $table->dropColumn('observaciones');
            }
            if (Schema::hasColumn('abogado_coordinaciones', 'validacion_estado')) {
                $table->dropColumn('validacion_estado');
            }
        });
    }
};

