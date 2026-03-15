<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_email_click_raw_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_campaign_id');
            $table->char('subscriber_email_hash', 64);
            $table->string('clicked_url', 2048)->nullable();
            $table->json('url_params')->nullable();
            $table->json('raw_data');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->unique(
                ['integration_id', 'external_campaign_id', 'subscriber_email_hash'],
                'cecrd_unique_click'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_email_click_raw_data');
    }
};
