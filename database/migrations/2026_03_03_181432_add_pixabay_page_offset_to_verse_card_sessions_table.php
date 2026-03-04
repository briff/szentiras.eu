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
            $table->unsignedInteger('pixabay_page')->default(1)->after('status');
            $table->unsignedInteger('pixabay_offset')->default(0)->after('pixabay_page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verse_card_sessions', function (Blueprint $table) {
            $table->dropColumn(['pixabay_page', 'pixabay_offset']);
        });
    }
};
