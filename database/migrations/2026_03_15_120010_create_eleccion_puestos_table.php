<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eleccion_puestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();

            $table->string('dd', 2);
            $table->string('mm', 3);
            $table->string('zz', 2);
            $table->string('pp', 2);

            $table->string('codigo_puesto')->nullable();
            $table->string('departamento')->nullable();
            $table->string('municipio')->nullable();
            $table->string('puesto')->nullable();
            $table->string('comuna')->nullable();
            $table->string('direccion')->nullable();

            $table->unsignedInteger('mesas_total')->default(0);

            $table->timestamps();

            $table->unique(['eleccion_id', 'dd', 'mm', 'zz', 'pp'], 'eleccion_puestos_unique');
            $table->index(['dd', 'mm', 'zz', 'pp'], 'eleccion_puestos_geo_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eleccion_puestos');
    }
};
