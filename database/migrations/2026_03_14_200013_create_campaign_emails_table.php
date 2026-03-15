<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raw_data_id')->nullable()->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('integration_id')->nullable()->index();
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('from_name', 100)->nullable();
            $table->string('from_email', 254)->nullable();
            $table->string('type', 50)->nullable();
            $table->unsignedInteger('sent')->nullable();
            $table->unsignedInteger('delivered')->nullable();
            $table->unsignedInteger('bounced')->nullable();
            $table->unsignedInteger('complaints')->nullable();
            $table->unsignedInteger('unsubscribes')->nullable();
            $table->unsignedInteger('opens')->nullable();
            $table->unsignedInteger('unique_opens')->nullable();
            $table->unsignedInteger('clicks')->nullable();
            $table->unsignedInteger('unique_clicks')->nullable();
            $table->decimal('platform_revenue', 12, 2)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'sent_at']);
            $table->index(['integration_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_emails');
    }
};
