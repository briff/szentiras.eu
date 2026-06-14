<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Test\Common\TestCase;

class ImportGreekTest extends TestCase
{
    use RefreshDatabase;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    /**
     * dictionary.txt contains two extended-Strong rows for G0001: G0001G
     * ("α, Ἀλφα" / Alpha) and G0001H ("ἆ"). The TXT importer keys by the base
     * number, so the second row would win without the XML override.
     */
    private function fakeDictionaryTxt(): string
    {
        $separator = str_repeat('=', 120);

        $rows = [
            "G0001\tG0001G =\tG0001G\tα, Ἀλφα\tAlpha\tG:N-LI\tAlpha\tdefinition",
            "G0001\tG0001H =\tG0001H\tἆ\ta\tG:INJ\tah!\tdefinition",
            "G0002\tG0002 =\tG0002\tἈαρών\tAaron\tG:N\tAaron\tdefinition",
        ];

        return "header line\n{$separator}\n" . implode("\n", $rows) . "\n";
    }

    private function fakeDictionaryXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<root><entries>
<entry strongs="00001"><strongs>1</strongs><greek BETA="*A" unicode="Α" translit="A"/></entry>
<entry strongs="00002"><strongs>2</strongs><greek BETA="*)AARW/N" unicode="Ἀαρών" translit="Aarṓn"/></entry>
</entries></root>
XML;
    }

    public function test_lemma_is_taken_from_xml_unicode_field_for_duplicated_strong_numbers(): void
    {
        Storage::put('greek/dictionary.txt', $this->fakeDictionaryTxt());
        Storage::put('greek/dictionary.xml', $this->fakeDictionaryXml());

        $this->artisan('szentiras:import-greek', [
            '--skip-verses' => true,
        ])->assertSuccessful();

        $this->assertSame('Α', StrongWord::where('number', 1)->value('lemma'));
        $this->assertSame('A', StrongWord::where('number', 1)->value('transliteration'));
        $this->assertSame('Ἀαρών', StrongWord::where('number', 2)->value('lemma'));
    }

    public function test_falls_back_to_txt_lemma_when_xml_is_missing(): void
    {
        Storage::put('greek/dictionary.txt', $this->fakeDictionaryTxt());

        $this->artisan('szentiras:import-greek', [
            '--skip-verses' => true,
        ])->assertSuccessful();

        $this->assertSame('ἆ', StrongWord::where('number', 1)->value('lemma'));
    }

    /**
     * Reimporting the Strong words must keep existing rows' ids stable so the
     * greek_verse_strong_word pivot survives. A truncate-and-insert would reset
     * the id sequence and cascade-delete the pivot.
     */
    public function test_reimporting_strong_words_preserves_verse_pivot(): void
    {
        Storage::put('greek/dictionary.txt', $this->fakeDictionaryTxt());

        $strongWord = new StrongWord();
        $strongWord->number = 2;
        $strongWord->lemma = 'old';
        $strongWord->transliteration = 'old';
        $strongWord->normalized = 'old';
        $strongWord->save();
        $originalId = $strongWord->id;

        $greekVerse = $this->makeGreekVerse();
        $greekVerse->strongWords()->attach($originalId, ['position' => 0]);

        $this->artisan('szentiras:import-greek', [
            '--skip-verses' => true,
        ])->assertSuccessful();

        $reimported = StrongWord::where('number', 2)->firstOrFail();
        $this->assertSame($originalId, $reimported->id);
        $this->assertSame('Ἀαρών', $reimported->lemma);
        $this->assertTrue($greekVerse->strongWords()->where('strong_words.id', $originalId)->exists());
    }

    private function makeGreekVerse(): GreekVerse
    {
        $greekVerse = new GreekVerse();
        $greekVerse->source = 'OpenGNT';
        $greekVerse->usx_code = 'MAT';
        $greekVerse->chapter = 1;
        $greekVerse->verse = 1;
        $greekVerse->gepi = 'MAT_1_1';
        $greekVerse->text = 'Ἀαρών';
        $greekVerse->json = '[]';
        $greekVerse->strongs = 'Ἀαρών';
        $greekVerse->strong_transliterations = 'Aaron';
        $greekVerse->strong_normalizations = 'aaron';
        $greekVerse->save();

        return $greekVerse;
    }
}
