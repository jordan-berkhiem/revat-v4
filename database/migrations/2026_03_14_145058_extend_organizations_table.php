<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('timezone', 64)->default('UTC')->after('name');
            $table->boolean('support_access_enabled')->default(false)->after('timezone');
            $table->softDeletes()->after('updated_at');

            // Stored generated column: non-null only when not soft-deleted, enabling unique constraint
            $table->string('name_uniqueness_guard')->storedAs(
                'CASE WHEN deleted_at IS NULL THEN name ELSE NULL END'
            )->nullable()->after('support_access_enabled');
            $table->unique('name_uniqueness_guard', 'organizations_name_unique_when_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop generated column first (SQLite requires this before dropping dependent columns)
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique('organizations_name_unique_when_active');
            $table->dropColumn('name_uniqueness_guard');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'timezone',
                'support_access_enabled',
                'deleted_at',
            ]);
        });
    }
};
