<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('mesa_reportes', function (Blueprint $table) {
            if (!Schema::hasColumn('mesa_reportes', 'e14_foto_path')) {
                $table->string('e14_foto_path')->nullable()->after('e14_reportado_at');
            }
        });
    }

    public function down()
    {
        Schema::table('mesa_reportes', function (Blueprint $table) {
            if (Schema::hasColumn('mesa_reportes', 'e14_foto_path')) {
                $table->dropColumn('e14_foto_path');
            }
        });
    }
};
