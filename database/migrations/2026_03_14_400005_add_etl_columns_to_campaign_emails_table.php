<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('extraction_batch_id')->nullable()->index()->after('raw_data_id');
            $table->timestamp('transformed_at')->nullable()->index()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->dropIndex(['extraction_batch_id']);
            $table->dropIndex(['transformed_at']);
            $table->dropColumn(['extraction_batch_id', 'transformed_at']);
        });
    }
};
