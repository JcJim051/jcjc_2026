<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('abogado_phone_updates', function (Blueprint $table) {
            $table->id();
            // El esquema histórico usa tipos de ID distintos entre módulos.
            $table->unsignedBigInteger('abogado_id')->index();
            $table->unsignedBigInteger('reunion_id')->index();
            $table->unsignedBigInteger('asistencia_session_id')->index();
            $table->string('old_phone')->nullable();
            $table->string('new_phone');
            $table->string('source', 50)->default('asistencia');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('abogado_phone_updates');
    }
};
