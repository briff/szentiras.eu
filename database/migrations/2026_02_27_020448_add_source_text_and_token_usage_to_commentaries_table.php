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
            $table->text('source_text')->nullable();
            $table->integer('token_usage')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commentaries', function (Blueprint $table) {
            $table->dropColumn('source_text');
            $table->dropColumn('token_usage');
        });
    }
};
