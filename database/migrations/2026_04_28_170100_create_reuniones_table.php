<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('lugar')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();
        });

        Schema::create('abogado_reunion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abogado_id')->constrained('abogados')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['abogado_id', 'reunion_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('abogado_reunion');
        Schema::dropIfExists('reuniones');
    }
};

