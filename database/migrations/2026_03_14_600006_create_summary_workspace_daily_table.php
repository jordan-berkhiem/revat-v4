<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_workspace_daily', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->unsignedInteger('campaigns_count')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('cost', 14, 2)->default(0);
            $table->timestamp('summarized_at');

            $table->primary(['workspace_id', 'summary_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_workspace_daily');
    }
};
