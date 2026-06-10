<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('abogado_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('token', 80)->unique();
            $table->string('label')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('completed_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'active', 'expires_at'], 'abogado_access_tokens_lookup_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('abogado_access_tokens');
    }
};
