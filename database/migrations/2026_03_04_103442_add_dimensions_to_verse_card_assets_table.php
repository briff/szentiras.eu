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
        Schema::table('verse_card_assets', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('bytes');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verse_card_assets', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
