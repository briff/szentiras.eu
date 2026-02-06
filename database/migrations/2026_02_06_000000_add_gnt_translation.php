<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        DB::table('translations')->insert([
            'abbrev' => 'GNT',
            'name' => 'Open Greek New Testament',
            'order' => 21,
            'denom' => 'egyéb',
            'lang' => 'görög',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('translations')->where('abbrev', 'GNT')->delete();
    }
};
