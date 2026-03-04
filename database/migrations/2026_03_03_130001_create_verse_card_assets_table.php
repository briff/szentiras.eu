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
        Schema::create('verse_card_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->string('kind'); // candidate, selected, final
            $table->string('state'); // queued, downloading, ready, deleted, failed
            $table->bigInteger('pixabay_id')->nullable()->index();
            $table->string('pixabay_user')->nullable();
            $table->string('pixabay_page_url')->nullable();
            $table->text('remote_url')->nullable();
            $table->string('disk')->default('ephemeral');
            $table->string('path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->bigInteger('bytes')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index('session_id');
            $table->foreign('session_id')
                ->references('id')
                ->on('verse_card_sessions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verse_card_assets');
    }
};
