<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commentaries', function (Blueprint $table) {
            // Make commentary_text nullable (pending commentaries have no text yet)
            $table->text('commentary_text')->nullable()->change();

            // Status: pending, processing, completed, failed
            $table->string('status')->default('pending');
            // Laravel job ID for tracking
            $table->string('job_id')->nullable();
            // Error details if generation failed
            $table->text('error_message')->nullable();
            // Timestamps for job lifecycle
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Indexes for faster lookups
            $table->index('status');
            $table->index('job_id');
            $table->index(['translation_id', 'usx_code', 'status']);
        });

        // Update existing commentaries to have status 'completed' and set timestamps
        DB::table('commentaries')
            ->whereNull('status')
            ->update([
                'status' => 'completed',
                'started_at' => DB::raw('created_at'),
                'completed_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commentaries', function (Blueprint $table) {
            // Remove added columns
            $table->dropIndex(['status']);
            $table->dropIndex(['job_id']);
            $table->dropIndex(['translation_id', 'usx_code', 'status']);

            $table->dropColumn('status');
            $table->dropColumn('job_id');
            $table->dropColumn('error_message');
            $table->dropColumn('started_at');
            $table->dropColumn('completed_at');

            // Restore commentary_text to not nullable (cannot revert safely, keep nullable)
            // $table->text('commentary_text')->nullable(false)->change();
        });
    }
};
