<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_anonymous_id')
                ->nullable()
                ->constrained('anonymous_ids')
                ->nullOnDelete();
            $table->foreignId('receiver_anonymous_id')
                ->nullable()
                ->constrained('anonymous_ids')
                ->nullOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('contact_messages')
                ->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['sender_anonymous_id', 'created_at']);
            $table->index(['receiver_anonymous_id', 'created_at']);
            $table->index(['parent_id']);
            $table->index(['resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};