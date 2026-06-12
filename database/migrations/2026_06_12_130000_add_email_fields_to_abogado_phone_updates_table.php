<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('abogado_phone_updates')) {
            Schema::create('abogado_phone_updates', function (Blueprint $table) {
                $table->id();
                // Mantener IDs indexados sin FK evita incompatibilidades con tablas heredadas.
                $table->unsignedBigInteger('abogado_id')->index();
                $table->unsignedBigInteger('reunion_id')->index();
                $table->unsignedBigInteger('asistencia_session_id')->index();
                $table->string('old_phone')->nullable();
                $table->string('new_phone');
                $table->string('old_email')->nullable();
                $table->string('new_email')->nullable();
                $table->string('source', 50)->default('asistencia');
                $table->timestamps();
            });

            return;
        }

        if (!Schema::hasColumn('abogado_phone_updates', 'old_email')) {
            Schema::table('abogado_phone_updates', function (Blueprint $table) {
                $table->string('old_email')->nullable()->after('new_phone');
            });
        }

        if (!Schema::hasColumn('abogado_phone_updates', 'new_email')) {
            Schema::table('abogado_phone_updates', function (Blueprint $table) {
                $table->string('new_email')->nullable()->after('old_email');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('abogado_phone_updates')) {
            return;
        }

        $columns = array_filter(['old_email', 'new_email'], function ($column) {
            return Schema::hasColumn('abogado_phone_updates', $column);
        });

        if ($columns) {
            Schema::table('abogado_phone_updates', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
