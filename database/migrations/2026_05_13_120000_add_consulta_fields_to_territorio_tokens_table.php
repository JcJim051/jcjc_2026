<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('territorio_tokens', function (Blueprint $table) {
            $table->boolean('es_consulta')->default(false)->after('comuna');
            $table->json('municipios')->nullable()->after('es_consulta');
        });
    }

    public function down()
    {
        Schema::table('territorio_tokens', function (Blueprint $table) {
            $table->dropColumn(['es_consulta', 'municipios']);
        });
    }
};
