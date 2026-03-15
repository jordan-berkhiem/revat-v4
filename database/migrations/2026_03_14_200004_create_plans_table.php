<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('stripe_price_monthly', 50)->nullable();
            $table->string('stripe_price_yearly', 50)->nullable();
            $table->unsignedInteger('max_workspaces')->default(1);
            $table->unsignedInteger('max_integrations_per_workspace')->default(2);
            $table->unsignedInteger('max_users')->default(1);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
