<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribution_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained('attribution_connectors')->cascadeOnDelete();
            $table->binary('key_hash', 32)->nullable(false);
            $table->string('key_value', 500);
            $table->timestamps();

            $table->unique(['workspace_id', 'connector_id', 'key_hash'], 'ak_unique_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_keys');
    }
};
