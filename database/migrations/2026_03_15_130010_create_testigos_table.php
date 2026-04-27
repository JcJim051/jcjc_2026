<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('testigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('tipo')->nullable();
            $table->string('nivel')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('testigos');
    }
};
