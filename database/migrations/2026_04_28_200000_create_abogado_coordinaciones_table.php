<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('abogado_coordinaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abogado_id')->constrained('abogados')->cascadeOnDelete();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->string('codpuesto');
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->index(['abogado_id', 'eleccion_id']);
            $table->index(['eleccion_id', 'codpuesto']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('abogado_coordinaciones');
    }
};

