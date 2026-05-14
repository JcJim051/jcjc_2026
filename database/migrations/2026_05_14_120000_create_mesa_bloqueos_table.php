<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mesa_bloqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_puesto_id')->constrained('eleccion_puestos')->cascadeOnDelete();
            $table->unsignedInteger('mesa_num');
            $table->string('origen')->default('excel_externo');
            $table->string('lote')->nullable();
            $table->unsignedInteger('fila_origen')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['eleccion_id', 'eleccion_puesto_id', 'mesa_num'], 'mesa_bloqueos_unique');
            $table->index(['eleccion_id', 'mesa_num'], 'mesa_bloqueos_eleccion_mesa_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mesa_bloqueos');
    }
};
