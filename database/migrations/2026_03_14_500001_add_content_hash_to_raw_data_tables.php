<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'campaign_email_raw_data',
            'campaign_email_click_raw_data',
            'conversion_sale_raw_data',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'content_hash')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->char('content_hash', 64)->nullable()->after('raw_data')->index();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'campaign_email_raw_data',
            'campaign_email_click_raw_data',
            'conversion_sale_raw_data',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'content_hash')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropIndex("{$table}_content_hash_index");
                    $blueprint->dropColumn('content_hash');
                });
            }
        }
    }
};
