<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_attribution_by_effort', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('effort_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->string('model', 30);
            $table->unsignedInteger('attributed_conversions')->default(0);
            $table->decimal('attributed_revenue', 14, 2)->default(0);
            $table->decimal('total_weight', 10, 4)->default(0);
            $table->timestamp('summarized_at');

            $table->primary(['workspace_id', 'effort_id', 'summary_date', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_attribution_by_effort');
    }
};
