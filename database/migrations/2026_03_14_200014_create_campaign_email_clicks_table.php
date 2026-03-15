<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_email_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raw_data_id')->nullable()->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('integration_id')->nullable()->index();
            $table->foreignId('campaign_email_id')->nullable()->constrained('campaign_emails')->nullOnDelete();
            $table->unsignedBigInteger('identity_hash_id')->nullable()->index();
            $table->timestamp('clicked_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'clicked_at']);
            $table->index(['identity_hash_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_email_clicks');
    }
};
