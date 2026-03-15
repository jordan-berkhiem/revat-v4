<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efforts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type', 30)->nullable();
            $table->string('name');
            $table->string('code', 50);
            $table->string('status', 30)->default('active');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'code']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'channel_type']);
            $table->index(['workspace_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efforts');
    }
};
