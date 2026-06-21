<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePuestoReportesTable extends Migration
{
    public function up()
    {
        Schema::create('puesto_reportes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleccion_id');
            $table->unsignedBigInteger('eleccion_puesto_id');
            $table->unsignedBigInteger('territorio_token_id')->nullable();
            $table->unsignedBigInteger('abogado_id')->nullable();
            $table->unsignedInteger('testigos_presentes')->nullable();
            $table->unsignedBigInteger('created_by_abogado_id')->nullable();
            $table->unsignedBigInteger('updated_by_abogado_id')->nullable();
            $table->timestamps();

            $table->unique('eleccion_puesto_id', 'puesto_reportes_puesto_unique');
            $table->index(['eleccion_id', 'eleccion_puesto_id'], 'puesto_reportes_eleccion_puesto_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('puesto_reportes');
    }
}
