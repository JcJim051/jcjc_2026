<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('referidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_puesto_id')->constrained('eleccion_puestos')->cascadeOnDelete();
            $table->foreignId('territorio_token_id')->constrained('territorio_tokens')->cascadeOnDelete();

            $table->unsignedInteger('mesa_num');
            $table->string('cedula_pdf_path');

            $table->string('estado')->default('referido');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->unique(['persona_id', 'eleccion_id'], 'referidos_persona_eleccion_unique');
            $table->index(['eleccion_id', 'eleccion_puesto_id'], 'referidos_eleccion_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('referidos');
    }
};
