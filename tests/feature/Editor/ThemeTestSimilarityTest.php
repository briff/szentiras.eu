<?php

namespace Tests\Feature\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pgvector\Laravel\Vector;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Models\GreekVerseEmbedding;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\TestCase;

class ThemeTestSimilarityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the EditorService to always return true for currentIsEditor()
        $this->mock(EditorService::class, function ($mock) {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
        
        // Set a test embedding model
        config(['settings.ai.embeddingModel' => 'text-embedding-3-small']);
    }

    /**
     * Hook called after the database is refreshed.
     * Reset PostgreSQL sequences to prevent ID collisions.
     */
    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    public function test_similarity_search_with_valid_gepi(): void
    {
        // Create a test embedding vector (512 dimensions)
        $testVector = new Vector(array_fill(0, 512, 0.1));
        
        // Create a Greek verse embedding
        $greekEmbedding = GreekVerseEmbedding::create([
            'gepi' => '1PE_2_3',
            'source' => 'BMT',
            'usx_code' => '1PE',
            'chapter' => 2,
            'verse' => 3,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector,
        ]);

        // Create test themes with similar embeddings
        $theme1 = Theme::create([
            'hungarian_keyword' => 'Szeretet',
            'embedding' => $testVector,
            'photo_keywords' => 'love, compassion',
        ]);

        $theme2 = Theme::create([
            'hungarian_keyword' => 'Irgalom',
            'embedding' => new Vector(array_fill(0, 512, 0.11)),
            'photo_keywords' => 'mercy, grace',
        ]);

        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '1PE_2_3',
            'limit' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'results' => [
                '1PE_2_3' => [
                    'found',
                    'gepi',
                    'themes' => [
                        '*' => [
                            'id',
                            'hungarian_keyword',
                            'photo_keywords',
                            'similarity',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($response->json('results.1PE_2_3.found'));
        $this->assertCount(2, $response->json('results.1PE_2_3.themes'));
    }

    public function test_similarity_search_with_multiple_gepis(): void
    {
        $testVector1 = new Vector(array_fill(0, 512, 0.1));
        $testVector2 = new Vector(array_fill(0, 512, 0.2));

        GreekVerseEmbedding::create([
            'gepi' => '1PE_2_3',
            'source' => 'BMT',
            'usx_code' => '1PE',
            'chapter' => 2,
            'verse' => 3,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector1,
        ]);

        GreekVerseEmbedding::create([
            'gepi' => '1JN_1_1',
            'source' => 'BMT',
            'usx_code' => '1JN',
            'chapter' => 1,
            'verse' => 1,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector2,
        ]);

        Theme::create([
            'hungarian_keyword' => 'Szeretet',
            'embedding' => $testVector1,
        ]);

        Theme::create([
            'hungarian_keyword' => 'Irgalom',
            'embedding' => $testVector2,
        ]);

        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '1PE_2_3, 1JN_1_1',
            'limit' => 10,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('results.1PE_2_3.found'));
        $this->assertTrue($response->json('results.1JN_1_1.found'));
    }

    public function test_similarity_search_with_nonexistent_gepi(): void
    {
        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => 'NONEXISTENT_1_1',
            'limit' => 10,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('results.NONEXISTENT_1_1.found'));
        $this->assertStringContainsString('not found', $response->json('results.NONEXISTENT_1_1.message'));
    }

    public function test_similarity_search_with_empty_gepis(): void
    {
        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '',
            'limit' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_similarity_search_with_whitespace_only_gepis(): void
    {
        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '   ,  ,   ',
            'limit' => 10,
        ]);

        $response->assertStatus(400);
    }

    public function test_similarity_search_respects_limit(): void
    {
        $testVector = new Vector(array_fill(0, 512, 0.1));

        GreekVerseEmbedding::create([
            'gepi' => '1PE_2_3',
            'source' => 'BMT',
            'usx_code' => '1PE',
            'chapter' => 2,
            'verse' => 3,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector,
        ]);

        // Create 15 themes
        for ($i = 0; $i < 15; $i++) {
            Theme::create([
                'hungarian_keyword' => "Téma{$i}",
                'embedding' => new Vector(array_fill(0, 512, 0.1 + ($i * 0.001))),
            ]);
        }

        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '1PE_2_3',
            'limit' => 5,
        ]);

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json('results.1PE_2_3.themes')));
    }

    public function test_similarity_search_missing_gepis_parameter(): void
    {
        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'limit' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_similarity_search_invalid_limit(): void
    {
        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '1PE_1_1',
            'limit' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_similarity_search_with_comma_separated_gepis_with_spaces(): void
    {
        $testVector1 = new Vector(array_fill(0, 512, 0.1));
        $testVector2 = new Vector(array_fill(0, 512, 0.2));

        GreekVerseEmbedding::create([
            'gepi' => '1PE_2_3',
            'source' => 'BMT',
            'usx_code' => '1PE',
            'chapter' => 2,
            'verse' => 3,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector1,
        ]);

        GreekVerseEmbedding::create([
            'gepi' => '1JN_1_1',
            'source' => 'BMT',
            'usx_code' => '1JN',
            'chapter' => 1,
            'verse' => 1,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector2,
        ]);

        Theme::create([
            'hungarian_keyword' => 'Szeretet',
            'embedding' => $testVector1,
        ]);

        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '  1PE_2_3  ,  1JN_1_1  ',
            'limit' => 10,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('results.1PE_2_3.found'));
        $this->assertTrue($response->json('results.1JN_1_1.found'));
    }

    public function test_similarity_search_returns_similarity_scores(): void
    {
        $testVector = new Vector(array_fill(0, 512, 0.1));

        GreekVerseEmbedding::create([
            'gepi' => '1PE_2_3',
            'source' => 'BMT',
            'usx_code' => '1PE',
            'chapter' => 2,
            'verse' => 3,
            'model' => 'text-embedding-3-small',
            'embedding' => $testVector,
        ]);

        Theme::create([
            'hungarian_keyword' => 'Szeretet',
            'embedding' => $testVector,
            'photo_keywords' => 'love',
        ]);

        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => '1PE_2_3',
            'limit' => 10,
        ]);

        $response->assertStatus(200);
        $themes = $response->json('results.1PE_2_3.themes');
        $this->assertNotEmpty($themes);
        
        foreach ($themes as $theme) {
            $this->assertArrayHasKey('similarity', $theme);
            $this->assertIsNumeric($theme['similarity']);
            $this->assertGreaterThanOrEqual(0, $theme['similarity']);
            $this->assertLessThanOrEqual(1, $theme['similarity']);
        }
    }
}
