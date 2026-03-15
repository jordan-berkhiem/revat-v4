<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('extraction_batches', 'force_transform')) {
            Schema::table('extraction_batches', function (Blueprint $table) {
                $table->boolean('force_transform')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('extraction_batches', 'force_transform')) {
            Schema::table('extraction_batches', function (Blueprint $table) {
                $table->dropColumn('force_transform');
            });
        }
    }
};
