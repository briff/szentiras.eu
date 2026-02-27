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
        Schema::create('openai_batches', function (Blueprint $table) {
            $table->id();
            $table->string('input_file_id')->nullable()->comment('OpenAI file ID for uploaded JSONL');
            $table->string('batch_id')->nullable()->comment('OpenAI batch ID after creation');
            $table->string('status')->default('queued')->comment('queued, validating, in_progress, finalizing, completed, failed, expired, cancelled');
            $table->string('output_file_id')->nullable()->comment('OpenAI output file ID');
            $table->string('error_file_id')->nullable()->comment('OpenAI error file ID');
            $table->string('endpoint')->default('/v1/responses');
            $table->jsonb('meta')->nullable()->comment('Additional metadata');
            $table->timestamps();

            $table->index('status');
            $table->index('batch_id');
            $table->index('input_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openai_batches');
    }
};
