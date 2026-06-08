<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE referidos MODIFY cedula_pdf_path VARCHAR(255) NULL');

        Schema::create('referido_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('territorio_token_id')->constrained('territorio_tokens')->cascadeOnDelete();
            $table->string('filename')->nullable();
            $table->string('status')->default('preview');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referido_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('referido_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('cedula')->nullable();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('puesto_texto')->nullable();
            $table->unsignedInteger('mesa_num')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('eleccion_puesto_id')->nullable()->constrained('eleccion_puestos')->nullOnDelete();
            $table->string('status')->default('error');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('referido_id')->nullable()->constrained('referidos')->nullOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['cedula']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('referido_import_rows');
        Schema::dropIfExists('referido_import_batches');

        DB::table('referidos')->whereNull('cedula_pdf_path')->update(['cedula_pdf_path' => '']);
        DB::statement('ALTER TABLE referidos MODIFY cedula_pdf_path VARCHAR(255) NOT NULL');
    }
};
