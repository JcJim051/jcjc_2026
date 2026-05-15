<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eleccion_puesto_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('eleccion_puesto_id')->constrained('eleccion_puestos')->cascadeOnDelete();
            $table->unsignedInteger('meta_pactada');
            $table->string('origen')->default('manual');
            $table->string('lote')->nullable();
            $table->timestamps();

            $table->unique(['eleccion_id', 'eleccion_puesto_id'], 'eleccion_puesto_metas_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eleccion_puesto_metas');
    }
};

