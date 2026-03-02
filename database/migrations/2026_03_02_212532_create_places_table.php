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
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->comment('Unique identifier from the import source');
            $table->string('type')->comment('e.g., mountain range, river, well');
            $table->string('friendly_id')->comment('Human-readable name like "Jerusalem"');
            $table->text('comment')->nullable();
            $table->string('lon_lat', 50)->nullable()->comment('Longitude,latitude as comma-separated string');
            $table->timestamps();
            
            $table->index('type');
            $table->index('friendly_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
