<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribution_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('campaign_integration_id')->index();
            $table->string('campaign_data_type', 50);
            $table->unsignedBigInteger('conversion_integration_id')->index();
            $table->string('conversion_data_type', 50);
            $table->json('field_mappings');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['campaign_integration_id', 'conversion_integration_id', 'campaign_data_type', 'conversion_data_type'],
                'ac_unique_connector'
            );
            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_connectors');
    }
};
