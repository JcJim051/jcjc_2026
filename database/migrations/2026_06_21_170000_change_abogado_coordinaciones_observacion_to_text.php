<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE abogado_coordinaciones MODIFY observacion TEXT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE abogado_coordinaciones MODIFY observacion VARCHAR(255) NULL");
    }
};
