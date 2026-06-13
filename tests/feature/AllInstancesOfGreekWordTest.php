<?php

namespace SzentirasHu\Test;

use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class AllInstancesOfGreekWordTest extends FastDatabaseTestCase
{
    private function createStrongWordWithOccurrence(): StrongWord
    {
        $strongWord = new StrongWord();
        $strongWord->number = 976;
        $strongWord->lemma = 'βίβλος';
        $strongWord->transliteration = 'biblos';
        $strongWord->normalized = 'biblos';
        $strongWord->save();

        // The book "Ter" (usx_code GEN) belongs to the default test translation.
        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
        $greekVerse->gepi = 'GEN_1_1';
        $greekVerse->usx_code = 'GEN';
        $greekVerse->chapter = 1;
        $greekVerse->verse = 1;
        $greekVerse->text = 'Βίβλος γενέσεως Ἀβραάμ.';
        $greekVerse->json = '[]';
        $greekVerse->strongs = 'βίβλος γένεσις Ἀβραάμ';
        $greekVerse->strong_transliterations = 'biblos genesis Abraam';
        $greekVerse->strong_normalizations = '';
        $greekVerse->save();

        $strongWord->greekVerses()->attach($greekVerse->id, ['position' => 0]);

        return $strongWord;
    }

    public function test_find_all_page_renders_clickable_and_highlighted_greek_words(): void
    {
        $strongWord = $this->createStrongWordWithOccurrence();

        $response = $this->get("/ai-greek/find-all/{$strongWord->number}");

        $response->assertStatus(200);
        // Every Greek word is clickable and carries its lookup data.
        $response->assertSee('greekWord clickable-greek', false);
        $response->assertSee('data-usx="GEN"', false);
        $response->assertSee('data-i="1"', false);
        // The occurrence of the searched word (index 0) is highlighted.
        $response->assertSee('class="greekWord clickable-greek matched"', false);
        $response->assertSee('data-i="0"', false);
        // The shared word panel drawer is included.
        $response->assertSee('id="greekWordOffcanvas"', false);
    }
}
