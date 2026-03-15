<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_campaign_daily', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->unsignedInteger('campaigns_count')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('bounced')->default(0);
            $table->unsignedInteger('complaints')->default(0);
            $table->unsignedInteger('unsubscribes')->default(0);
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('unique_opens')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('unique_clicks')->default(0);
            $table->decimal('platform_revenue', 14, 2)->default(0);
            $table->timestamp('summarized_at');

            $table->primary(['workspace_id', 'summary_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_campaign_daily');
    }
};
