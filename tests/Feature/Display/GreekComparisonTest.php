<?php

namespace Tests\Feature\Display;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class GreekComparisonTest extends FastDatabaseTestCase
{
    private const TEST_TRANSLATION_ID = 1001;

    private function setUpNewTestamentData(): void
    {
        $book = new Book();
        $book->order = 200;
        $book->abbrev = 'Mt';
        $book->name = 'Máté';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->usx_code = 'MAT';
        $book->translation_id = self::TEST_TRANSLATION_ID;
        $book->save();

        $verse = new Verse();
        $verse->trans = self::TEST_TRANSLATION_ID;
        $verse->gepi = '99300001001000';
        $verse->usx_code = 'MAT';
        $verse->book_id = $book->id;
        $verse->chapter = 1;
        $verse->numv = 1;
        $verse->tip = 6;
        $verse->verse = 'Magyar Máté evangéliuma';
        $verse->verseroot = 'verseroot';
        $verse->ido = '2025-02-05';
        $verse->save();

        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
        $greekVerse->gepi = 'MAT.1.1';
        $greekVerse->usx_code = 'MAT';
        $greekVerse->chapter = 1;
        $greekVerse->verse = 1;
        $greekVerse->text = 'Βιβλος γενεσεως';
        $greekVerse->json = '[]';
        $greekVerse->strongs = 'G976 G1078';
        $greekVerse->strong_transliterations = 'biblos geneseos';
        $greekVerse->strong_normalizations = '';
        $greekVerse->save();
    }

    private function enableGreekComparison(): void
    {
        $gnt = Translation::where('abbrev', 'GNT')->firstOrFail();
        \Config::set('settings.enabledTranslations', [self::TEST_TRANSLATION_ID, $gnt->id, 9]);
        \Config::set('translations.ids', \Config::get('translations.ids', []) + [
            self::TEST_TRANSLATION_ID => 'TESTTRANS',
            $gnt->id => 'GNT',
        ]);
        \Config::set('translations.definitions.TESTTRANS', [
            'verseTypes' => [
                'text' => [6, 901],
                'poemLine' => [902],
            ],
            'textSource' => 'local',
            'id' => self::TEST_TRANSLATION_ID,
            'order' => 50,
            'toc_heading_levels' => '5-9',
        ]);
        Cache::flush();
    }

    public function test_greek_text_is_shown_clickable_next_to_the_hungarian_translation(): void
    {
        $this->setUpNewTestamentData();
        $this->enableGreekComparison();

        $response = $this->get('/TESTTRANS/Mt1,1?compare=GNT');

        $response->assertStatus(200);

        // Both the Hungarian text and the Greek text are present.
        $response->assertSeeText('Magyar Máté evangéliuma');
        $response->assertSee('Βιβλος', false);
        $response->assertSee('γενεσεως', false);

        // Each Greek word is a clickable span wired to the word explanation panel.
        $response->assertSee('class="greekWord clickable-greek"', false);
        $response->assertSee('data-usx="MAT"', false);
        $response->assertSee('id="greekWordOffcanvas"', false);

        // The verse number is rendered and stays out of the AI-tools driven `.parsedVerses`
        // wrapper so it remains visible regardless of the AI-tools state.
        $response->assertSee('<span class="numv"><sup>1</sup></span>', false);
        $response->assertDontSee('parsedVerses greek', false);
    }
}
