<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pgvector\Laravel\Vector;
use SzentirasHu\Data\Entity\EmbeddedExcerpt;
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
        
        // Create a verse embedding
        EmbeddedExcerpt::factory()->create([
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
                'found',
                'gepis_count',
                'gepis_found',
                'gepis_not_found',
                'themes' => [
                    '*' => [
                        'id',
                        'hungarian_keyword',
                        'photo_keywords',
                        'similarity',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($response->json('results.found'));
        $this->assertCount(2, $response->json('results.themes'));
    }

    public function test_similarity_search_with_multiple_gepis(): void
    {
        $testVector1 = new Vector(array_fill(0, 512, 0.1));
        $testVector2 = new Vector(array_fill(0, 512, 0.2));

        EmbeddedExcerpt::factory()->create([
            'gepi' => '1PE_2_3',
            'embedding' => $testVector1,
        ]);

        EmbeddedExcerpt::factory()->create([
            'gepi' => '1JN_1_1',
            'usx_code' => '1JN',
            'chapter' => 1,
            'verse' => 1,
            'embedding' => $testVector2,
            'reference' => '1Jn 1,1',
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
        $this->assertTrue($response->json('results.found'));
        $this->assertContains('1PE_2_3', $response->json('results.gepis_found'));
        $this->assertContains('1JN_1_1', $response->json('results.gepis_found'));
    }

    public function test_similarity_search_with_nonexistent_gepi(): void
    {
        $response = $this->postJson(route('editor.themes.testSimilarity'), [
            'gepis' => 'NONEXISTENT_1_1',
            'limit' => 10,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('No valid embeddings found', $response->json('error'));
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

        EmbeddedExcerpt::factory()->create([
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
        $this->assertLessThanOrEqual(5, count($response->json('results.themes')));
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

        EmbeddedExcerpt::factory()->create([
            'gepi' => '1PE_2_3',
            'embedding' => $testVector1,
        ]);

        EmbeddedExcerpt::factory()->create([
            'gepi' => '1JN_1_1',
            'usx_code' => '1JN',
            'chapter' => 1,
            'verse' => 1,
            'embedding' => $testVector2,
            'reference' => '1Jn 1,1',
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
        $this->assertTrue($response->json('results.found'));
        $this->assertContains('1PE_2_3', $response->json('results.gepis_found'));
        $this->assertContains('1JN_1_1', $response->json('results.gepis_found'));
    }

    public function test_similarity_search_returns_similarity_scores(): void
    {
        $testVector = new Vector(array_fill(0, 512, 0.1));

        EmbeddedExcerpt::factory()->create([
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
        $themes = $response->json('results.themes');
        $this->assertNotEmpty($themes);
        
        foreach ($themes as $theme) {
            $this->assertArrayHasKey('similarity', $theme);
            $this->assertIsNumeric($theme['similarity']);
            $this->assertGreaterThanOrEqual(0, $theme['similarity']);
            $this->assertLessThanOrEqual(1, $theme['similarity']);
        }
    }
}
