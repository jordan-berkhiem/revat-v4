<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('extraction_batch_id')->index();
            $table->string('external_id')->index();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_records');
    }
};
