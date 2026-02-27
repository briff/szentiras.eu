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
        Schema::table('commentaries', function (Blueprint $table) {
            $table->string('verification_level', 20)
                  ->default('none')
                  ->after('commentary_text')
                  ->comment('Verification level: none, sanity, theology');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commentaries', function (Blueprint $table) {
            $table->dropColumn('verification_level');
        });
    }
};
