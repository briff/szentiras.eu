<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use SzentirasHu\Data\ProtestantBookNames;

return new class extends Migration {
    public function up(): void
    {
        foreach (ProtestantBookNames::NAMES as $translationAbbrev => $names) {
            $translationId = DB::table('translations')->where('abbrev', $translationAbbrev)->value('id');
            if (!$translationId) {
                continue;
            }
            foreach ($names as $usxCode => $name) {
                DB::table('books')
                    ->where('translation_id', $translationId)
                    ->where('usx_code', $usxCode)
                    ->update([
                        'name' => $name,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Data correction; the previous (Catholic-style) names are not restored.
    }
};
