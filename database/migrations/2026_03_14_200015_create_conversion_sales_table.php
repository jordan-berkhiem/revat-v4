<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raw_data_id')->nullable()->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('integration_id')->nullable()->index();
            $table->string('external_id');
            $table->unsignedBigInteger('identity_hash_id')->nullable()->index();
            $table->decimal('revenue', 12, 2)->nullable();
            $table->decimal('payout', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'converted_at']);
            $table->index(['integration_id', 'external_id']);
            $table->index(['identity_hash_id', 'converted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_sales');
    }
};
