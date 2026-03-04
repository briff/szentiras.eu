<?php

namespace Tests\Feature\Service;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Vector;
use SzentirasHu\Data\Entity\EmbeddedExcerpt;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;
use SzentirasHu\Service\Theme\ThemeService;
use SzentirasHu\Test\Common\TestCase;

class ThemeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ThemeService $themeService;
    protected TextService $textService;
    protected TranslationService $translationService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['settings.ai.embeddingModel' => 'text-embedding-3-small']);

        // Create mocks using Mockery
        $this->textService = \Mockery::mock(TextService::class);
        $this->translationService = \Mockery::mock(TranslationService::class);

        $this->themeService = new ThemeService(
            $this->textService,
            $this->translationService,
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_empty_collection_when_no_verses_found(): void
    {
        $translation = Translation::factory()->create(['abbrev' => 'SZIT']);
        $this->translationService
            ->shouldReceive('getByAbbreviation')
            ->with('SZIT')
            ->andReturn($translation);

        $this->textService
            ->shouldReceive('getTranslatedVerses')
            ->andReturn([]);

        $result = $this->themeService->findSimilarThemes('Jn 3:16', 'SZIT');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_returns_empty_collection_when_no_embeddings_found(): void
    {
        $translation = Translation::factory()->create(['abbrev' => 'SZIT']);
        $this->translationService
            ->shouldReceive('getByAbbreviation')
            ->with('SZIT')
            ->andReturn($translation);

        // Mock verse containers with gepis
        $verseContainer = \Mockery::mock(\SzentirasHu\Service\VerseContainer::class);
        $verseData = new \SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData(3, 16);
        $verseData->gepi = 'JN_3_16';
        $verseContainer->shouldReceive('getParsedVerses')->andReturn([$verseData]);

        $this->textService
            ->shouldReceive('getTranslatedVerses')
            ->andReturn([$verseContainer]);

        // No EmbeddedExcerpt records exist

        $result = $this->themeService->findSimilarThemes('Jn 3:16', 'SZIT');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_returns_similar_themes_based_on_embedding_centroid(): void
    {
        $translation = Translation::factory()->create(['abbrev' => 'SZIT']);
        $this->translationService
            ->shouldReceive('getByAbbreviation')
            ->with('SZIT')
            ->andReturn($translation);

        // Mock verse containers with gepis
        $verseContainer = \Mockery::mock(\SzentirasHu\Service\VerseContainer::class);
        $verseData = new \SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData(3, 16);
        $verseData->gepi = 'JN_3_16';
        $verseContainer->shouldReceive('getParsedVerses')->andReturn([$verseData]);

        $this->textService
            ->shouldReceive('getTranslatedVerses')
            ->andReturn([$verseContainer]);

        // Create an embedding for the gepi using forceCreate to bypass mass assignment
        // Use 512 dimensions as required by the embedding model
        $vector = new Vector(array_fill(0, 512, 0.1));
        EmbeddedExcerpt::forceCreate([
            'hash' => 'test',
            'gepi' => 'JN_3_16',
            'model' => 'text-embedding-3-small',
            'embedding' => $vector,
            'translation_abbrev' => 'SZIT',
            'scope' => \SzentirasHu\Data\Entity\EmbeddedExcerptScope::Verse,
            'reference' => 'Jn 3:16',
            'usx_code' => 'JN',
            'chapter' => 3,
            'verse' => 16,
            'to_chapter' => null,
            'to_verse' => null,
        ]);

        // Create a theme with the same embedding (should be highly similar)
        $theme = Theme::create([
            'hungarian_keyword' => 'Szeretet',
            'embedding' => $vector,
            'photo_keywords' => 'love',
        ]);

        $result = $this->themeService->findSimilarThemes('Jn 3:16', 'SZIT', 10);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($theme->id, $result[0]['id']);
        $this->assertEquals('Szeretet', $result[0]['hungarian_keyword']);
        $this->assertGreaterThan(0.9, $result[0]['similarity']);
    }
}