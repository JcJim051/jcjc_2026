<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('elecciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo');
            $table->date('fecha')->nullable();
            $table->string('estado')->default('activa');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('elecciones');
    }
};
