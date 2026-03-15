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
        // Step 1: Create the base table with FK constraints
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Step 2: Add virtual generated columns separately
        // MySQL does not allow stored generated columns that reference FK columns,
        // so we use virtual columns instead (which support unique indexes in both MySQL and SQLite).
        Schema::table('workspaces', function (Blueprint $table) {
            // Enforce one default workspace per org
            $table->integer('default_uniqueness_guard')->virtualAs(
                'CASE WHEN is_default = 1 AND deleted_at IS NULL THEN organization_id ELSE NULL END'
            )->nullable()->after('is_default');

            // Allow workspace name reuse after soft-delete
            $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
            $concatExpr = $isSqlite
                ? "(organization_id || ':' || name)"
                : "CONCAT(organization_id, ':', name)";
            $table->string('name_uniqueness_guard')->virtualAs(
                "CASE WHEN deleted_at IS NULL THEN {$concatExpr} ELSE NULL END"
            )->nullable()->after('default_uniqueness_guard');

            $table->unique('default_uniqueness_guard', 'workspaces_one_default_per_org');
            $table->unique('name_uniqueness_guard', 'workspaces_name_unique_per_org_when_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
