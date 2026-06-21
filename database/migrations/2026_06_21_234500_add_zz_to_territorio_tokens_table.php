<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('territorio_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('territorio_tokens', 'zz')) {
                $table->string('zz', 2)->nullable()->after('mm');
                $table->index(['eleccion_id', 'dd', 'mm', 'zz'], 'territorio_tokens_eleccion_geo_zona_idx');
            }
        });
    }

    public function down()
    {
        Schema::table('territorio_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('territorio_tokens', 'zz')) {
                $table->dropIndex('territorio_tokens_eleccion_geo_zona_idx');
                $table->dropColumn('zz');
            }
        });
    }
};
