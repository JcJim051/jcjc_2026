<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comision_mesa_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_mesa_id')->constrained('eleccion_mesas')->cascadeOnDelete();
            $table->foreignId('territorio_token_id')->nullable()->constrained('territorio_tokens')->nullOnDelete();
            $table->string('autor_nombre', 255);
            $table->text('comentario');
            $table->timestamps();

            $table->index(['eleccion_id', 'eleccion_mesa_id'], 'comision_comentarios_mesa_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comision_mesa_comentarios');
    }
};
