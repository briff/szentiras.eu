<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('translations')->insert([
            'id' => 9,
            'abbrev' => 'KAL',
            'name' => 'Káldi György Fordítása (1626) (Tárkányi-féle revízió, 1865)',
            'order' => 12,
            'denom' => 'katolikus',
            'lang' => 'magyar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('translations')->where('abbrev', 'KAL')->delete();
    }
};
