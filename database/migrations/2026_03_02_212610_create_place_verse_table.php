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
        Schema::create('place_verse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->string('book_code', 3)->comment('USX book code');
            $table->unsignedInteger('chapter_number');
            $table->unsignedInteger('verse_number');
            $table->timestamps();
            
            $table->index(['book_code', 'chapter_number', 'verse_number']);
            $table->index('place_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('place_verse');
    }
};
