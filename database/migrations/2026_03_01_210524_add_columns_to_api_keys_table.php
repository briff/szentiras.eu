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
            $table->string('name')->after('id');
            $table->string('key_prefix', 8)->unique()->after('name');
            $table->string('key_hash')->after('key_prefix');
            $table->boolean('is_internal')->default(false)->after('key_hash');
            $table->boolean('is_active')->default(true)->after('is_internal');
            $table->integer('throttle_rate')->nullable()->comment('Requests per minute for external keys')->after('is_active');
            $table->foreignId('created_by')->nullable()->constrained('anonymous_ids')->nullOnDelete()->after('throttle_rate');
            $table->text('description')->nullable()->after('created_by');
            $table->timestamp('last_used_at')->nullable()->after('description');
            $table->softDeletes()->after('updated_at');

            $table->index(['key_prefix', 'is_active']);
            $table->index('is_internal');
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex(['key_prefix', 'is_active']);
            $table->dropIndex(['is_internal']);
            $table->dropIndex(['last_used_at']);
            
            $table->dropColumn([
                'name',
                'key_prefix',
                'key_hash',
                'is_internal',
                'is_active',
                'throttle_rate',
                'created_by',
                'description',
                'last_used_at',
            ]);
            
            $table->dropSoftDeletes();
        });
    }
};
