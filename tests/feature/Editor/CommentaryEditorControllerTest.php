<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\TestCase;

class CommentaryEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the EditorService to always return true for currentIsEditor()
        $this->mock(EditorService::class, function ($mock) {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
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
     * Test that editing commentary text preserves existing references.
     */
    public function testEditingCommentaryTextPreservesReferences(): void
    {
        // Create a translation
        $translation = Translation::factory()->create();

        // Create a commentary with references
        $commentaryData = [
            'commentary_text' => 'Original commentary text',
            'references' => [
                ['ref' => 'John 3:16', 'reason' => 'Key verse'],
                ['ref' => 'Romans 5:8', 'reason' => 'Supporting verse'],
            ],
        ];

        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => 'MAT',
            'commentary_text' => json_encode($commentaryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'completed',
            'verification_level' => 'none',
        ]);

        // Edit only the commentary text
        $response = $this->put(route('editor.commentaries.update', $commentary->id), [
            'commentary_text' => 'Updated commentary text with new content',
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.commentaries.show', $commentary));
        $response->assertSessionHas('success', 'Kommentár szövege frissítve.');

        // Reload the commentary from database
        $commentary->refresh();

        // Decode the updated commentary text
        $updatedData = json_decode($commentary->commentary_text, true);

        // Verify the commentary text was updated
        $this->assertEquals('Updated commentary text with new content', $updatedData['commentary_text']);

        // Verify the references were preserved
        $this->assertCount(2, $updatedData['references']);
        $this->assertEquals('John 3:16', $updatedData['references'][0]['ref']);
        $this->assertEquals('Key verse', $updatedData['references'][0]['reason']);
        $this->assertEquals('Romans 5:8', $updatedData['references'][1]['ref']);
        $this->assertEquals('Supporting verse', $updatedData['references'][1]['reason']);
    }

    /**
     * Test that editing references updates them correctly.
     */
    public function testEditingReferencesUpdatesCorrectly(): void
    {
        // Create a translation
        $translation = Translation::factory()->create();

        // Create a commentary with references
        $commentaryData = [
            'commentary_text' => 'Original commentary text',
            'references' => [
                ['ref' => 'John 3:16', 'reason' => 'Key verse'],
            ],
        ];

        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => 'MAT',
            'commentary_text' => json_encode($commentaryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'completed',
            'verification_level' => 'none',
        ]);

        // Edit the references
        $response = $this->put(route('editor.commentaries.update', $commentary->id), [
            'references_form_submitted' => '1',
            'references' => [
                ['ref' => 'John 3:16', 'reason' => 'Updated reason'],
                ['ref' => 'Luke 15:11', 'reason' => 'New reference'],
            ],
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.commentaries.show', $commentary));
        $response->assertSessionHas('success', 'Hivatkozások frissítve.');

        // Reload the commentary from database
        $commentary->refresh();

        // Decode the updated commentary text
        $updatedData = json_decode($commentary->commentary_text, true);

        // Verify the commentary text was preserved
        $this->assertEquals('Original commentary text', $updatedData['commentary_text']);

        // Verify the references were updated
        $this->assertCount(2, $updatedData['references']);
        $this->assertEquals('John 3:16', $updatedData['references'][0]['ref']);
        $this->assertEquals('Updated reason', $updatedData['references'][0]['reason']);
        $this->assertEquals('Luke 15:11', $updatedData['references'][1]['ref']);
        $this->assertEquals('New reference', $updatedData['references'][1]['reason']);
    }

    /**
     * Test that editing both commentary text and references updates both.
     */
    public function testEditingBothTextAndReferencesUpdatesBoth(): void
    {
        // Create a translation
        $translation = Translation::factory()->create();

        // Create a commentary with references
        $commentaryData = [
            'commentary_text' => 'Original commentary text',
            'references' => [
                ['ref' => 'John 3:16', 'reason' => 'Key verse'],
            ],
        ];

        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => 'MAT',
            'commentary_text' => json_encode($commentaryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'completed',
            'verification_level' => 'none',
        ]);

        // Edit both text and references
        $response = $this->put(route('editor.commentaries.update', $commentary->id), [
            'commentary_text' => 'Updated commentary text',
            'references_form_submitted' => '1',
            'references' => [
                ['ref' => 'John 3:16', 'reason' => 'Updated reason'],
                ['ref' => 'Luke 15:11', 'reason' => 'New reference'],
            ],
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.commentaries.show', $commentary));
        $response->assertSessionHas('success', 'Kommentár szövege és hivatkozások frissítve.');

        // Reload the commentary from database
        $commentary->refresh();

        // Decode the updated commentary text
        $updatedData = json_decode($commentary->commentary_text, true);

        // Verify both were updated
        $this->assertEquals('Updated commentary text', $updatedData['commentary_text']);
        $this->assertCount(2, $updatedData['references']);
        $this->assertEquals('John 3:16', $updatedData['references'][0]['ref']);
        $this->assertEquals('Updated reason', $updatedData['references'][0]['reason']);
        $this->assertEquals('Luke 15:11', $updatedData['references'][1]['ref']);
        $this->assertEquals('New reference', $updatedData['references'][1]['reason']);
    }

    /**
     * Test that clearing all references works when references form is submitted empty.
     */
    public function testClearingAllReferencesWorks(): void
    {
        // Create a translation
        $translation = Translation::factory()->create();

        // Create a commentary with references
        $commentaryData = [
            'commentary_text' => 'Original commentary text',
            'references' => [
                ['ref' => 'John 3:16', 'reason' => 'Key verse'],
                ['ref' => 'Romans 5:8', 'reason' => 'Supporting verse'],
            ],
        ];

        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => 'MAT',
            'commentary_text' => json_encode($commentaryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'completed',
            'verification_level' => 'none',
        ]);

        // Submit the references form with the hidden field but no references
        $response = $this->put(route('editor.commentaries.update', $commentary->id), [
            'references_form_submitted' => '1',
            // No references array - this means clear all
        ]);

        // Verify the response redirects successfully
        $response->assertRedirect(route('editor.commentaries.show', $commentary));
        $response->assertSessionHas('success', 'Hivatkozások frissítve.');

        // Reload the commentary from database
        $commentary->refresh();

        // Decode the updated commentary text
        $updatedData = json_decode($commentary->commentary_text, true);

        // Verify the commentary text was preserved
        $this->assertEquals('Original commentary text', $updatedData['commentary_text']);

        // Verify all references were cleared
        $this->assertCount(0, $updatedData['references']);
    }
}
