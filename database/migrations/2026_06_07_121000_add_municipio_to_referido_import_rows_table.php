<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('referido_import_rows', function (Blueprint $table) {
            $table->string('municipio_texto')->nullable()->after('puesto_texto');
        });
    }

    public function down()
    {
        Schema::table('referido_import_rows', function (Blueprint $table) {
            $table->dropColumn('municipio_texto');
        });
    }
};
