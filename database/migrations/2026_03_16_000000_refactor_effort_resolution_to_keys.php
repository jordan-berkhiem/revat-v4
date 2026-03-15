<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add effort_id to attribution_keys
        Schema::table('attribution_keys', function (Blueprint $table) {
            $table->foreignId('effort_id')->nullable()->after('key_value')
                ->constrained('efforts')->nullOnDelete();
        });

        // 2. Add description and auto_generated to efforts
        Schema::table('efforts', function (Blueprint $table) {
            $table->text('description')->nullable()->after('code');
            $table->boolean('auto_generated')->default(false)->after('description');
        });

        // 3. Drop effort_id from campaign_emails
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->dropForeign(['effort_id']);
            $table->dropColumn('effort_id');
        });

        // 4. Truncate stale data (old per-field keys and results)
        DB::table('attribution_results')->delete();
        DB::table('attribution_keys')->delete(); // cascades to attribution_record_keys
    }

    public function down(): void
    {
        // Re-add effort_id to campaign_emails
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->foreignId('effort_id')->nullable()->after('integration_id')
                ->constrained('efforts')->nullOnDelete();
        });

        // Drop auto_generated and description from efforts
        Schema::table('efforts', function (Blueprint $table) {
            $table->dropColumn('auto_generated');
            $table->dropColumn('description');
        });

        // Drop effort_id from attribution_keys
        Schema::table('attribution_keys', function (Blueprint $table) {
            $table->dropForeign(['effort_id']);
            $table->dropColumn('effort_id');
        });
    }
};
