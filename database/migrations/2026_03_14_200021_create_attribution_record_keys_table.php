<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribution_record_keys', function (Blueprint $table) {
            $table->foreignId('connector_id')->constrained('attribution_connectors')->cascadeOnDelete();
            $table->foreignId('attribution_key_id')->constrained('attribution_keys')->cascadeOnDelete();
            $table->string('record_type', 30);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['connector_id', 'record_type', 'record_id']);
            $table->index(['attribution_key_id', 'record_type', 'connector_id'], 'idx_key_match');
            $table->index(['record_type', 'record_id'], 'idx_record_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_record_keys');
    }
};
