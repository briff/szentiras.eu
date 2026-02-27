<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('openai_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('openai_batch_id')->constrained('openai_batches')->cascadeOnDelete();
            $table->string('custom_id')->unique()->comment('Used to match responses (e.g., "gen_{batch_id}_1")');
            $table->unsignedBigInteger('source_id')->nullable()->comment('Optional reference to commentaries or other source entities');
            $table->string('status')->default('queued')->comment('queued, submitted, succeeded, failed');
            $table->jsonb('payload')->nullable()->comment('OpenAI API parameters for this item');
            $table->text('error')->nullable()->comment('Error message if failed');
            $table->timestamps();

            $table->index('openai_batch_id');
            $table->index('status');
            $table->index('source_id');
            $table->index('custom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openai_batch_items');
    }
};
