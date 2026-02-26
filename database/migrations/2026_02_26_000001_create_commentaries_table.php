<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commentaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->constrained('translations');
            $table->string('usx_code', 3);
            $table->text('commentary_text');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['translation_id', 'usx_code']);
        });

        Schema::create('commentary_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commentary_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('start_chapter');
            $table->unsignedInteger('start_verse');
            $table->unsignedInteger('end_chapter');
            $table->unsignedInteger('end_verse');

            $table->index(['commentary_id']);
            $table->index(['start_chapter', 'start_verse', 'end_chapter', 'end_verse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commentary_ranges');
        Schema::dropIfExists('commentaries');
    }
};