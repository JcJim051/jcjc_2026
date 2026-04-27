<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('cedula')->nullable()->unique();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('personas');
    }
};
