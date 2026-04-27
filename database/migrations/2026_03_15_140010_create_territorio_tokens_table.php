<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('territorio_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->string('dd', 2);
            $table->string('mm', 3);
            $table->string('comuna')->nullable();
            $table->string('token')->unique();
            $table->string('responsable')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['eleccion_id', 'dd', 'mm'], 'territorio_tokens_geo_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('territorio_tokens');
    }
};
