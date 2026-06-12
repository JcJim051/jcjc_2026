<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('abogado_phone_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abogado_id')->constrained('abogados')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->foreignId('asistencia_session_id')->constrained('asistencia_sessions')->cascadeOnDelete();
            $table->string('old_phone')->nullable();
            $table->string('new_phone');
            $table->string('source', 50)->default('asistencia');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('abogado_phone_updates');
    }
};
