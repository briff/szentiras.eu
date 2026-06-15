<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\TestCase;

class StrongWordEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EditorService::class, function ($mock) {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    private function createStrongWord(int $number = 2424): StrongWord
    {
        $strongWord = new StrongWord();
        $strongWord->number = $number;
        $strongWord->lemma = 'Ἰησοῦς';
        $strongWord->transliteration = 'Iesous';
        $strongWord->normalized = 'ιησους';
        $strongWord->save();

        return $strongWord;
    }

    private function createMeaning(int $number, string $meaning, string $source = 'test', int $order = 0): void
    {
        $dictionaryMeaning = new DictionaryMeaning();
        $dictionaryMeaning->strong_word_number = $number;
        $dictionaryMeaning->source = $source;
        $dictionaryMeaning->meaning = $meaning;
        $dictionaryMeaning->explanation = "{$meaning} magyarázat";
        $dictionaryMeaning->order = $order;
        $dictionaryMeaning->save();
    }

    public function testUpdatePersistsEntryAndReplacesMeanings(): void
    {
        $strongWord = $this->createStrongWord();
        $this->createMeaning($strongWord->number, 'régi', 'old');

        $response = $this->put(route('editor.strongWords.update', $strongWord->id), [
            'paradigm' => 'Ἰησοῦς, -οῦ (hímnem)',
            'etymology' => 'A héber névből.',
            'notes' => 'Néhány megjegyzés.',
            'meanings' => [
                ['meaning' => 'Jézus', 'explanation' => 'A názáreti Jézus.'],
                ['meaning' => 'Józsué', 'explanation' => 'Az ószövetségi vezető.'],
            ],
        ]);

        $response->assertRedirect(route('editor.strongWords.show', $strongWord));
        $response->assertSessionHas('success');

        $entry = DictionaryEntry::where('strong_word_number', $strongWord->number)->firstOrFail();
        $this->assertEquals('Ἰησοῦς, -οῦ (hímnem)', $entry->paradigm);
        $this->assertEquals('editor', $entry->source);

        $meanings = DictionaryMeaning::where('strong_word_number', $strongWord->number)->orderBy('order')->get();
        $this->assertCount(2, $meanings);
        $this->assertEquals('Jézus', $meanings[0]->meaning);
        $this->assertEquals('Józsué', $meanings[1]->meaning);
        $this->assertEquals(1, $meanings[1]->order);
    }

    public function testGenerateReturnsPreviewWithoutPersisting(): void
    {
        $strongWord = $this->createStrongWord();

        $json = json_encode([
            'word' => 'Ἰησοῦς, -οῦ (hímnem)',
            'meanings' => [
                ['meaning' => 'Jézus', 'explanation' => 'A názáreti Jézus Krisztus.'],
            ],
            'etymology' => 'A héber Jehosua névből.',
            'notes' => 'Fontos megjegyzés.',
        ], JSON_UNESCAPED_UNICODE);

        $this->mock(AiPromptService::class, function ($mock) use ($strongWord, $json) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $name, bool $isBatch, array $placeholders) use ($strongWord) {
                    return $name === 'strong_word_translation'
                        && $placeholders['greek_word'] === $strongWord->lemma;
                })
                ->andReturn('raw-response');
            $mock->shouldReceive('extractTextAndTokens')
                ->once()
                ->with('raw-response')
                ->andReturn([$json, 1234]);
        });

        $response = $this->postJson(route('editor.strongWords.generate', $strongWord->id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'tokenUsage' => 1234,
            'translation' => [
                'word' => 'Ἰησοῦς, -οῦ (hímnem)',
                'etymology' => 'A héber Jehosua névből.',
            ],
        ]);

        // Preview must not touch the database.
        $this->assertDatabaseMissing('dictionary_entries', ['strong_word_number' => $strongWord->number]);
        $this->assertDatabaseMissing('dictionary_meanings', ['strong_word_number' => $strongWord->number]);
    }

    public function testGenerateReturnsErrorOnBadResponse(): void
    {
        $strongWord = $this->createStrongWord();

        $this->mock(AiPromptService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('raw-response');
            $mock->shouldReceive('extractTextAndTokens')->once()->andReturn(['not json', 0]);
        });

        $response = $this->postJson(route('editor.strongWords.generate', $strongWord->id));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function testIndexListsStrongWordsWithDictionaryEntries(): void
    {
        $strongWord = $this->createStrongWord();
        $this->createMeaning($strongWord->number, 'Jézus');

        $response = $this->get(route('editor.strongWords.index'));

        $response->assertOk();
        $response->assertSee('Ἰησοῦς');
    }
}
