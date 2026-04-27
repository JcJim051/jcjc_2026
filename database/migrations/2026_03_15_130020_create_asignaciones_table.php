<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testigo_id')->constrained('testigos')->cascadeOnDelete();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_mesa_id')->constrained('eleccion_mesas')->cascadeOnDelete();

            $table->string('rol')->nullable();
            $table->string('estado')->default('activo');

            $table->timestamps();

            $table->unique(['testigo_id', 'eleccion_id', 'eleccion_mesa_id'], 'asignaciones_unique');
            $table->index(['eleccion_id', 'eleccion_mesa_id'], 'asignaciones_eleccion_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('asignaciones');
    }
};
