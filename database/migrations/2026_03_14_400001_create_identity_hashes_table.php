<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->binary('hash', 32);
            $table->string('type', 20)->default('email');
            $table->string('hash_algorithm', 20)->default('sha256');
            $table->string('normalized_email_domain', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['workspace_id', 'hash', 'type']);
            $table->index(['workspace_id', 'normalized_email_domain']);
            $table->index(['workspace_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_hashes');
    }
};
