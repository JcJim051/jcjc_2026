<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eleccion_mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_puesto_id')->constrained('eleccion_puestos')->cascadeOnDelete();

            $table->unsignedInteger('mesa_num');
            $table->string('mesa_key')->unique();

            $table->timestamps();

            $table->unique(['eleccion_id', 'eleccion_puesto_id', 'mesa_num'], 'eleccion_mesas_unique');
            $table->index(['eleccion_id', 'mesa_num'], 'eleccion_mesas_eleccion_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eleccion_mesas');
    }
};
