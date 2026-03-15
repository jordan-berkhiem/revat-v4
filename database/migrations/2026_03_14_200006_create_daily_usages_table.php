<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('campaigns_synced')->default(0);
            $table->unsignedInteger('conversions_synced')->default(0);
            $table->unsignedInteger('active_integrations')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'recorded_on']);
            $table->index(['organization_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_usages');
    }
};
