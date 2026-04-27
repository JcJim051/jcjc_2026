<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('candidatos', function (Blueprint $table) {
            $table->unsignedTinyInteger('campo_e14')
                ->nullable()
                ->after('partido');
        });
    }

    public function down()
    {
        Schema::table('candidatos', function (Blueprint $table) {
            $table->dropColumn('campo_e14');
        });
    }
};
