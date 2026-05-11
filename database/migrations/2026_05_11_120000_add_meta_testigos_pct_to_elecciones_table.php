<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('elecciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('meta_testigos_pct')->nullable()->after('alcance_mm');
        });
    }

    public function down()
    {
        Schema::table('elecciones', function (Blueprint $table) {
            $table->dropColumn('meta_testigos_pct');
        });
    }
};
