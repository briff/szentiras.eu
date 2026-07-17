<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $this->moveKaroliLamentations('SIR', 'LAM');
    }

    public function down(): void
    {
        $this->moveKaroliLamentations('LAM', 'SIR');
    }

    private function moveKaroliLamentations(string $from, string $to): void
    {
        $translationId = DB::table('translations')->where('abbrev', 'KG')->value('id');
        if (!$translationId) {
            return;
        }
        DB::table('books')
            ->where('translation_id', $translationId)
            ->where('usx_code', $from)
            ->update([
                'usx_code' => $to,
                'updated_at' => now(),
            ]);
    }
};
