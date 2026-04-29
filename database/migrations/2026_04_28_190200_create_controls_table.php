<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_abogado', 50);
            $table->foreignId('asistencia_session_id')->constrained('asistencia_sessions')->cascadeOnDelete();
            $table->dateTime('fecha')->nullable();
            $table->string('lugar')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->unique(['asistencia_session_id', 'codigo_abogado'], 'controls_session_abogado_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('controls');
    }
};

