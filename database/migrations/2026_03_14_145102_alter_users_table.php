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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->after('remember_token')
                ->constrained('organizations')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable()->after('current_organization_id');
            $table->index('deactivated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
            $table->dropIndex(['deactivated_at']);
            $table->dropColumn(['current_organization_id', 'deactivated_at']);
        });
    }
};
