<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eleccion_mesa_candidatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_mesa_id')->constrained('eleccion_mesas')->cascadeOnDelete();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['eleccion_mesa_id', 'candidato_id'], 'mesa_candidato_unique');
            $table->index(['eleccion_id', 'candidato_id'], 'mesa_candidato_eleccion_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eleccion_mesa_candidatos');
    }
};

