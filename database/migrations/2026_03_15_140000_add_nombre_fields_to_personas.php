<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->string('nombre_original')->nullable()->after('cedula');
            $table->string('nombre_normalizado')->nullable()->after('nombre_original');
        });
    }

    public function down()
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['nombre_original', 'nombre_normalizado']);
        });
    }
};
