<?php

namespace Tests\Feature\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Service\Search\EmbeddingResult;
use SzentirasHu\Service\Search\SemanticSearchService;
use SzentirasHu\Test\Common\TestCase;

class ThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the EditorService to always return true for currentIsEditor()
        $this->mock(EditorService::class, function ($mock) {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
        
        // Mock the SemanticSearchService
        $this->mock(SemanticSearchService::class, function ($mock) {
            $mock->shouldReceive('generateVector')
                ->andReturn(new EmbeddingResult(array_fill(0, 512, 0.1), 10));
            
            $mock->shouldReceive('findClosestVersesForTheme')
                ->andReturn([]);
        });
    }

    /**
     * Hook called after the database is refreshed.
     * Reset PostgreSQL sequences to prevent ID collisions.
     */
    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    /**
     * Test that updating a theme with only photo_keywords works.
     */
    public function testUpdateThemeWithPhotoKeywordsOnly(): void
    {
        // Create a theme
        $theme = Theme::create([
            'hungarian_keyword' => 'Szerelem',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'love, heart',
        ]);

        // Update only photo_keywords
        $response = $this->put(route('editor.themes.update', $theme->id), [
            'hungarian_keyword' => 'Szerelem',
            'photo_keywords' => 'love, heart, romance',
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.themes.show', $theme));
        $response->assertSessionHas('success', 'Téma sikeresen frissítve.');

        // Reload the theme from database
        $theme->refresh();

        // Verify the photo_keywords were updated
        $this->assertEquals('love, heart, romance', $theme->photo_keywords);
        // Verify the hungarian_keyword was not changed
        $this->assertEquals('Szerelem', $theme->hungarian_keyword);
    }

    /**
     * Test that updating a theme with a new hungarian_keyword regenerates the embedding.
     */
    public function testUpdateThemeWithNewHungarianKeywordRegeneratesEmbedding(): void
    {
        // Create a theme
        $theme = Theme::create([
            'hungarian_keyword' => 'Szerelem',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'love, heart',
        ]);

        $originalEmbedding = $theme->embedding->toArray();

        // Update the hungarian_keyword
        $response = $this->put(route('editor.themes.update', $theme->id), [
            'hungarian_keyword' => 'Szeretet',
            'photo_keywords' => 'love, heart',
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.themes.show', $theme));
        $response->assertSessionHas('success', 'Téma sikeresen frissítve.');

        // Reload the theme from database
        $theme->refresh();

        // Verify the hungarian_keyword was updated
        $this->assertEquals('Szeretet', $theme->hungarian_keyword);
    }

    /**
     * Test that cache is cleared when a theme is updated.
     */
    public function testCacheIsClearedWhenThemeIsUpdated(): void
    {
        // Create a theme
        $theme = Theme::create([
            'hungarian_keyword' => 'Szerelem',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'love, heart',
        ]);

        // Set some cache entries for this theme
        Cache::tags(["theme_{$theme->id}"])->put("theme_{$theme->id}_verses_SZIT_10", ['verse1', 'verse2'], now()->addHours(24));
        Cache::tags(["theme_{$theme->id}"])->put("theme_{$theme->id}_verses_KNB_10", ['verse3', 'verse4'], now()->addHours(24));

        // Verify cache entries exist
        $this->assertNotNull(Cache::tags(["theme_{$theme->id}"])->get("theme_{$theme->id}_verses_SZIT_10"));
        $this->assertNotNull(Cache::tags(["theme_{$theme->id}"])->get("theme_{$theme->id}_verses_KNB_10"));

        // Update the theme
        $response = $this->put(route('editor.themes.update', $theme->id), [
            'hungarian_keyword' => 'Szerelem',
            'photo_keywords' => 'love, heart, affection',
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.themes.show', $theme));

        // Verify cache entries were cleared
        $this->assertNull(Cache::tags(["theme_{$theme->id}"])->get("theme_{$theme->id}_verses_SZIT_10"));
        $this->assertNull(Cache::tags(["theme_{$theme->id}"])->get("theme_{$theme->id}_verses_KNB_10"));
    }

    /**
     * Test that validation fails when hungarian_keyword is empty.
     */
    public function testValidationFailsWhenHungarianKeywordIsEmpty(): void
    {
        // Create a theme
        $theme = Theme::create([
            'hungarian_keyword' => 'Szerelem',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'love, heart',
        ]);

        // Try to update with empty hungarian_keyword
        $response = $this->put(route('editor.themes.update', $theme->id), [
            'hungarian_keyword' => '',
            'photo_keywords' => 'love, heart',
        ]);

        // Verify the response has validation errors
        $response->assertSessionHasErrors('hungarian_keyword');
    }

    /**
     * Test that validation fails when hungarian_keyword is not unique.
     */
    public function testValidationFailsWhenHungarianKeywordIsNotUnique(): void
    {
        // Create two themes
        $theme1 = Theme::create([
            'hungarian_keyword' => 'Szerelem',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'love, heart',
        ]);

        $theme2 = Theme::create([
            'hungarian_keyword' => 'Szeretet',
            'embedding' => array_fill(0, 512, 0.2),
            'photo_keywords' => 'affection',
        ]);

        // Try to update theme2 with theme1's hungarian_keyword
        $response = $this->put(route('editor.themes.update', $theme2->id), [
            'hungarian_keyword' => 'Szerelem',
            'photo_keywords' => 'affection',
        ]);

        // Verify the response has validation errors
        $response->assertSessionHasErrors('hungarian_keyword');
    }

    /**
     * Test that updating a theme with null photo_keywords works.
     */
    public function testUpdateThemeWithNullPhotoKeywords(): void
    {
        // Create a theme
        $theme = Theme::create([
            'hungarian_keyword' => 'Szerelem',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'love, heart',
        ]);

        // Update with null photo_keywords
        $response = $this->put(route('editor.themes.update', $theme->id), [
            'hungarian_keyword' => 'Szerelem',
            'photo_keywords' => '',
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.themes.show', $theme));
        $response->assertSessionHas('success', 'Téma sikeresen frissítve.');

        // Reload the theme from database
        $theme->refresh();

        // Verify the photo_keywords were set to null
        $this->assertNull($theme->photo_keywords);
    }
}
