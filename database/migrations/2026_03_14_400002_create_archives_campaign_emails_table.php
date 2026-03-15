<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archives_campaign_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_data_id')->constrained('campaign_email_raw_data')->cascadeOnDelete();
            $table->unsignedBigInteger('extraction_batch_id')->index();
            $table->json('payload');
            $table->timestamp('archived_at')->useCurrent();

            $table->index(['workspace_id', 'archived_at']);
            $table->index(['raw_data_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archives_campaign_emails');
    }
};
