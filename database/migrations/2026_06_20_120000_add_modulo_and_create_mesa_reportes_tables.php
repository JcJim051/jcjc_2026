<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('territorio_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('territorio_tokens', 'modulo')) {
                $table->string('modulo', 40)->nullable()->after('municipios');
                $table->index(['eleccion_id', 'modulo'], 'territorio_tokens_eleccion_modulo_idx');
            }
        });

        Schema::create('mesa_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('territorio_token_id')->nullable()->constrained('territorio_tokens')->nullOnDelete();
            $table->foreignId('eleccion_puesto_id')->constrained('eleccion_puestos')->cascadeOnDelete();
            $table->foreignId('eleccion_mesa_id')->constrained('eleccion_mesas')->cascadeOnDelete();
            $table->foreignId('coordinacion_id')->nullable()->constrained('abogado_coordinaciones')->nullOnDelete();
            $table->foreignId('abogado_id')->nullable()->constrained('abogados')->nullOnDelete();
            $table->unsignedInteger('afluencia_9')->nullable();
            $table->unsignedInteger('afluencia_11')->nullable();
            $table->unsignedInteger('afluencia_14')->nullable();
            $table->unsignedInteger('censo_mesa')->nullable();
            $table->unsignedInteger('votos_en_urna')->nullable();
            $table->unsignedInteger('votos_incinerados')->nullable();
            $table->unsignedInteger('votos_blanco')->nullable();
            $table->unsignedInteger('votos_nulos')->nullable();
            $table->unsignedInteger('votos_no_marcados')->nullable();
            $table->json('e14_detalle')->nullable();
            $table->timestamp('e14_reportado_at')->nullable();
            $table->boolean('reconteo')->nullable();
            $table->boolean('reclamacion')->nullable();
            $table->unsignedInteger('jurados_firmaron')->nullable();
            $table->string('reclamacion_origen', 20)->nullable();
            $table->foreignId('reclamacion_candidato_id')->nullable()->constrained('candidatos')->nullOnDelete();
            $table->text('reclamacion_comentario')->nullable();
            $table->string('reclamacion_foto_path')->nullable();
            $table->timestamp('control_final_at')->nullable();
            $table->foreignId('created_by_abogado_id')->nullable()->constrained('abogados')->nullOnDelete();
            $table->foreignId('updated_by_abogado_id')->nullable()->constrained('abogados')->nullOnDelete();
            $table->timestamps();
            $table->unique(['eleccion_mesa_id'], 'mesa_reportes_mesa_unique');
            $table->index(['eleccion_id', 'eleccion_puesto_id'], 'mesa_reportes_eleccion_puesto_idx');
        });

        Schema::create('mesa_reporte_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesa_reporte_id')->constrained('mesa_reportes')->cascadeOnDelete();
            $table->foreignId('abogado_id')->nullable()->constrained('abogados')->nullOnDelete();
            $table->string('accion', 40);
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mesa_reporte_audits');
        Schema::dropIfExists('mesa_reportes');
        Schema::table('territorio_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('territorio_tokens', 'modulo')) {
                $table->dropIndex('territorio_tokens_eleccion_modulo_idx');
                $table->dropColumn('modulo');
            }
        });
    }
};
