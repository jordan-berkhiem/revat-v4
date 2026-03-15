<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->foreignId('effort_id')->nullable()->after('integration_id')
                ->constrained('efforts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->dropForeign(['effort_id']);
            $table->dropColumn('effort_id');
        });
    }
};
