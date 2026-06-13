<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\TestCase;

class GreekWordPanelTest extends TestCase
{
    use RefreshDatabase;

    private const GREEK_TRANSLATION_ID = 7;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createGreekBook(): void
    {
        $book = new Book();
        $book->translation_id = self::GREEK_TRANSLATION_ID;
        $book->name = 'Evangélium Máté szerint';
        $book->abbrev = 'Mt';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->order = 1;
        $book->usx_code = 'MAT';
        $book->save();

        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
        $greekVerse->gepi = 'MAT_1_1';
        $greekVerse->usx_code = 'MAT';
        $greekVerse->chapter = 1;
        $greekVerse->verse = 1;
        $greekVerse->text = 'Βίβλος γενέσεως Ἀβραάμ.¶';
        $greekVerse->json = '[]';
        $greekVerse->strongs = 'βίβλος γένεσις Ἀβραάμ';
        $greekVerse->strong_transliterations = 'biblos genesis Abraam';
        $greekVerse->strong_normalizations = '';
        $greekVerse->save();
    }

    public function test_annotated_words_carries_lookup_data_and_paragraph_break(): void
    {
        $verse = new GreekVerse();
        $verse->usx_code = 'MAT';
        $verse->chapter = 1;
        $verse->verse = 1;
        $verse->text = 'Βίβλος γενέσεως Ἀβραάμ.¶';
        $verse->strongs = 'βίβλος γένεσις Ἀβραάμ';
        $verse->strong_transliterations = 'biblos genesis Abraam';

        $words = $verse->annotatedWords();

        $this->assertCount(3, $words);

        $this->assertSame('Βίβλος', $words[0]['printed']);
        $this->assertSame(0, $words[0]['i']);
        $this->assertSame('βίβλος', $words[0]['strong']);
        $this->assertSame('biblos', $words[0]['translit']);
        $this->assertSame('MAT', $words[0]['usx_code']);
        $this->assertSame(1, $words[0]['chapter']);
        $this->assertSame(1, $words[0]['verse']);
        $this->assertFalse($words[0]['hasBreak']);

        // The paragraph marker is stripped from the printed word but recorded as a break.
        $this->assertSame('Ἀβραάμ.', $words[2]['printed']);
        $this->assertSame(2, $words[2]['i']);
        $this->assertTrue($words[2]['hasBreak']);
    }

    public function test_reading_page_renders_clickable_greek_words_and_panel(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt1');

        $response->assertStatus(200);
        $response->assertSee('class="greekWord clickable-greek"', false);
        $response->assertSee('data-usx="MAT"', false);
        $response->assertSee('data-i="0"', false);
        $response->assertSee('id="greekWordOffcanvas"', false);
    }
}
