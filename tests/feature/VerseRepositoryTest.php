<?php

namespace SzentirasHu\Test;
use App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Test\Common\TestCase;


/**

 */

class VerseRepositoryTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function testVersesInOrder() {
        $repo = App::make(\SzentirasHu\Data\Repository\VerseRepositoryEloquent::class);
        
        // Get actual verse IDs from the database
        $allVerses = \SzentirasHu\Data\Entity\Verse::orderBy('id')->limit(2)->get();
        $this->assertCount(2, $allVerses);
        
        $verseIds = $allVerses->pluck('id')->toArray();
        $verses = $repo->getVersesInOrder($verseIds);
        
        // Verify we got verses back in the correct order
        $this->assertCount(2, $verses);
        $verse = array_pop($verses);
        $this->assertIsObject($verse);
        $this->assertEquals('TESTTRANS', $verse->translation->abbrev);
        $this->assertEquals('Ter', $verse->book->abbrev);
    }

} 