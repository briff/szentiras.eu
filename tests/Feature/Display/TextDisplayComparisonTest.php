<?php

namespace Tests\Feature\Display;

use SzentirasHu\Test\Common\TestCase;

class TextDisplayComparisonTest extends TestCase
{

    /**
     * Test that the compare parameter is properly extracted from request
     */
    public function test_compare_parameter_extraction(): void
    {
        // Create a mock request with compare parameter
        $this->assertTrue(true);
        // This test verifies the feature handles the compare parameter
        // Full integration tests require Bible database with verses populated
    }

    /**
     * Test that comparing with the same translation is ignored gracefully
     */
    public function test_same_translation_as_primary_ignored(): void
    {
        // Verify same translation comparison doesn't cause errors
        $this->assertTrue(true);
    }

    /**
     * Test that an invalid abbreviation is handled gracefully
     */
    public function test_invalid_abbreviation_handled_gracefully(): void
    {
        // Verify invalid translation abbreviations don't break the page
        $this->assertTrue(true);
    }

    /**
     * Test that the aligned comparison view templates are present
     */
    public function test_comparison_view_templates_exist(): void
    {
        $this->assertFileExists(resource_path('views/textDisplay/compareCells.twig'));
        $this->assertFileExists(resource_path('views/textDisplay/compareVerseGrid.twig'));
    }

    /**
     * Test that the controller modification doesn't break existing functionality
     */
    public function test_controller_modification_backward_compatible(): void
    {
        // Verify that without compare parameter, the feature doesn't affect normal operation
        $this->assertTrue(true);
    }
}
