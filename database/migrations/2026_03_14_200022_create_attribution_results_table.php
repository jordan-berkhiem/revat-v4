<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribution_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained('attribution_connectors')->cascadeOnDelete();
            $table->string('conversion_type', 30);
            $table->unsignedBigInteger('conversion_id');
            $table->foreignId('effort_id')->constrained('efforts')->restrictOnDelete();
            $table->string('model', 30);
            $table->decimal('weight', 5, 4);
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['conversion_type', 'conversion_id', 'effort_id', 'model'], 'ar_unique_attribution');
            $table->index(['workspace_id', 'model']);
            $table->index(['effort_id', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_results');
    }
};
