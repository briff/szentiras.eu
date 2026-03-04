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
        Schema::table('verse_card_sessions', function (Blueprint $table) {
            $table->text('verse_text')->nullable()->after('verse_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verse_card_sessions', function (Blueprint $table) {
            $table->dropColumn('verse_text');
        });
    }
};
