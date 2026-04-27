<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('candidatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->integer('codigo');
            $table->string('nombre');
            $table->string('partido')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['eleccion_id', 'codigo'], 'candidatos_eleccion_codigo_unique');
            $table->index(['eleccion_id', 'activo'], 'candidatos_eleccion_activo_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('candidatos');
    }
};

