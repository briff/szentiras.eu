<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Test\Common\TestCase;

class GreekDictionaryTest extends TestCase
{
    use RefreshDatabase;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    private function createStrongWord(int $number, string $lemma, string $transliteration, string $normalized): StrongWord
    {
        $word = new StrongWord();
        $word->number = $number;
        $word->lemma = $lemma;
        $word->transliteration = $transliteration;
        $word->normalized = $normalized;
        $word->save();

        return $word;
    }

    private function addMeaning(int $strongWordNumber, string $meaning): void
    {
        $dictionaryMeaning = new DictionaryMeaning();
        $dictionaryMeaning->strong_word_number = $strongWordNumber;
        $dictionaryMeaning->order = 1;
        $dictionaryMeaning->meaning = $meaning;
        $dictionaryMeaning->explanation = '';
        $dictionaryMeaning->source = 'test';
        $dictionaryMeaning->save();
    }

    public function test_dictionary_index_lists_strong_words(): void
    {
        $this->createStrongWord(1, 'ἀγάπη', 'agapē', 'agape');
        $this->addMeaning(1, 'szeretet');
        $this->createStrongWord(2, 'λόγος', 'logos', 'logos');
        $this->addMeaning(2, 'beszéd');

        $response = $this->get('/gorog-szotar');

        $response->assertStatus(200);
        $response->assertSee('Görög szótár');
        $response->assertSee('ἀγάπη');
        $response->assertSee('λόγος');
    }

    public function test_dictionary_filter_returns_only_matching_words(): void
    {
        $this->createStrongWord(1, 'ἀγάπη', 'agapē', 'agape');
        $this->addMeaning(1, 'szeretet');
        $this->createStrongWord(2, 'λόγος', 'logos', 'logos');
        $this->addMeaning(2, 'beszéd');

        $response = $this->get('/gorog-szotar/filter?q=log');

        $response->assertStatus(200);
        $response->assertSee('λόγος');
        $response->assertDontSee('ἀγάπη');
    }

    public function test_dictionary_filter_with_no_match_shows_message(): void
    {
        $this->createStrongWord(1, 'ἀγάπη', 'agapē', 'agape');
        $this->addMeaning(1, 'szeretet');

        $response = $this->get('/gorog-szotar/filter?q=zzz');

        $response->assertStatus(200);
        $response->assertSee('Nincs a szűrésnek megfelelő szó.');
    }

    public function test_dictionary_excludes_words_without_occurrence_or_meaning(): void
    {
        $this->createStrongWord(6000, 'ἀγγέλλω', 'angellō', 'angello');

        $response = $this->get('/gorog-szotar');

        $response->assertStatus(200);
        $response->assertDontSee('ἀγγέλλω');
    }

    public function test_dictionary_index_shows_meaning(): void
    {
        $this->createStrongWord(3962, 'πατήρ', 'patēr', 'pater');
        $this->addMeaning(3962, 'atya');

        $response = $this->get('/gorog-szotar');

        $response->assertStatus(200);
        $response->assertSee('atya');
    }

    public function test_dictionary_filter_matches_by_meaning(): void
    {
        $this->createStrongWord(3962, 'πατήρ', 'patēr', 'pater');
        $this->addMeaning(3962, 'Atya');
        $this->createStrongWord(2, 'λόγος', 'logos', 'logos');
        $this->addMeaning(2, 'beszéd');

        $response = $this->get('/gorog-szotar/filter?q=atya');

        $response->assertStatus(200);
        $response->assertSee('πατήρ');
        $response->assertDontSee('λόγος');
    }

    public function test_dictionary_paginates_results(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $padded = sprintf('l%03d', $i);
            $this->createStrongWord($i, "λ{$i}", $padded, $padded);
            $this->addMeaning($i, "jelentés {$i}");
        }

        $firstPage = $this->get('/gorog-szotar');
        $firstPage->assertStatus(200);
        $firstPage->assertSee('1 / 2');
        $firstPage->assertSee('l001');
        $firstPage->assertDontSee('l021');

        $secondPage = $this->get('/gorog-szotar/filter?page=2');
        $secondPage->assertStatus(200);
        $secondPage->assertSee('l021');
        $secondPage->assertDontSee('l001');
    }
}
