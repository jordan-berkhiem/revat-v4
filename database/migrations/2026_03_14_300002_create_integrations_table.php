<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('name');
            $table->string('platform', 30);
            $table->json('data_types')->nullable();
            $table->text('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 30)->nullable();
            $table->text('last_sync_error')->nullable();
            $table->json('sync_statuses')->nullable();
            $table->boolean('sync_in_progress')->default(false);
            $table->unsignedInteger('sync_interval_minutes')->default(60);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'platform', 'name']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
