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
        Schema::table('api_keys', function (Blueprint $table) {
            // Rename is_active to enabled
            $table->renameColumn('is_active', 'enabled');
            
            // Rename created_by to created_by_anonymous_id
            $table->renameColumn('created_by', 'created_by_anonymous_id');
            
            // Add usage_count column
            $table->integer('usage_count')->default(0)->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->renameColumn('enabled', 'is_active');
            $table->renameColumn('created_by_anonymous_id', 'created_by');
            $table->dropColumn('usage_count');
        });
    }
};
