<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesa_reportes', function (Blueprint $table) {
            $table->unsignedInteger('testigos_presentes')->nullable()->after('afluencia_14');
        });
    }

    public function down(): void
    {
        Schema::table('mesa_reportes', function (Blueprint $table) {
            $table->dropColumn('testigos_presentes');
        });
    }
};
