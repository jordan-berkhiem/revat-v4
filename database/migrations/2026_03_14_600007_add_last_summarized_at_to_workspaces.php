<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workspaces', 'last_summarized_at')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->timestamp('last_summarized_at')->nullable()->after('is_default');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workspaces', 'last_summarized_at')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->dropColumn('last_summarized_at');
            });
        }
    }
};
