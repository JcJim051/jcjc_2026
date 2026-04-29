<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reuniones', function (Blueprint $table) {
            if (!Schema::hasColumn('reuniones', 'aforo')) {
                $table->string('aforo')->nullable()->after('lugar');
            }
        });
    }

    public function down()
    {
        Schema::table('reuniones', function (Blueprint $table) {
            if (Schema::hasColumn('reuniones', 'aforo')) {
                $table->dropColumn('aforo');
            }
        });
    }
};

