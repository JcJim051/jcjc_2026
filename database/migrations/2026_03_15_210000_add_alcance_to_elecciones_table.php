<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('elecciones', function (Blueprint $table) {
            $table->string('alcance_tipo')->nullable()->after('estado');
            $table->string('alcance_dd', 2)->nullable()->after('alcance_tipo');
            $table->string('alcance_mm')->nullable()->after('alcance_dd');
        });
    }

    public function down()
    {
        Schema::table('elecciones', function (Blueprint $table) {
            $table->dropColumn(['alcance_tipo', 'alcance_dd', 'alcance_mm']);
        });
    }
};

