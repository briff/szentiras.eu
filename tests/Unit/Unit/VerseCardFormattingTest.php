<?php

namespace Tests\Unit\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SzentirasHu\Http\Controllers\Display\VerseCardController;
use Mockery;

class VerseCardFormattingTest extends TestCase
{
    private VerseCardController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks for the dependencies
        $textService = Mockery::mock('SzentirasHu\Service\Text\TextService');
        $translationRepository = Mockery::mock('SzentirasHu\Data\Repository\TranslationRepository');
        $themeService = Mockery::mock('SzentirasHu\Service\Theme\ThemeService');
        
        $this->controller = new VerseCardController(
            $textService,
            $translationRepository,
            $themeService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that single verse without space is formatted with space.
     * E.g., 'Mt1,1' becomes 'Mt 1,1'
     */
    public function test_single_verse_without_space_is_formatted_with_space(): void
    {
        $method = new ReflectionMethod($this->controller, 'formatVerseReferences');

        $result = $method->invoke($this->controller, ['Mt1,1']);

        $this->assertEquals('Mt 1,1', $result);
    }

    /**
     * Test that single verse with space remains unchanged.
     * E.g., 'Mt 1,1' stays 'Mt 1,1'
     */
    public function test_single_verse_with_space_remains_unchanged(): void
    {
        $method = new ReflectionMethod($this->controller, 'formatVerseReferences');

        $result = $method->invoke($this->controller, ['Mt 1,1']);

        $this->assertEquals('Mt 1,1', $result);
    }

    /**
     * Test that consecutive verses are compressed with dash.
     * E.g., ['Mt1,2', 'Mt1,3'] becomes 'Mt 1,2-3'
     */
    public function test_consecutive_verses_are_compressed_with_dash(): void
    {
        $method = new ReflectionMethod($this->controller, 'formatVerseReferences');

        $result = $method->invoke($this->controller, ['Mt1,2', 'Mt1,3']);

        $this->assertEquals('Mt 1,2-3', $result);
    }

    /**
     * Test that non-consecutive verses are separated with dot.
     * E.g., ['Mt1,2', 'Mt1,5'] becomes 'Mt 1,2.5'
     */
    public function test_non_consecutive_verses_are_separated_with_dot(): void
    {
        $method = new ReflectionMethod($this->controller, 'formatVerseReferences');

        $result = $method->invoke($this->controller, ['Mt1,2', 'Mt1,5']);

        $this->assertEquals('Mt 1,2.5', $result);
    }

    /**
     * Test complex verse compression.
     * E.g., ['Mt1,2', 'Mt1,3', 'Mt1,4', 'Mt1,6', 'Mt1,7'] becomes 'Mt 1,2-4.6-7'
     */
    public function test_complex_verse_compression(): void
    {
        $method = new ReflectionMethod($this->controller, 'formatVerseReferences');

        $result = $method->invoke($this->controller, ['Mt1,2', 'Mt1,3', 'Mt1,4', 'Mt1,6', 'Mt1,7']);

        $this->assertEquals('Mt 1,2-4.6-7', $result);
    }

    /**
     * Test compressVerseNumbers method directly.
     */
    public function test_compress_verse_numbers_single_verse(): void
    {
        $method = new ReflectionMethod($this->controller, 'compressVerseNumbers');

        $result = $method->invoke($this->controller, [1]);

        $this->assertEquals('1', $result);
    }

    /**
     * Test compressVerseNumbers with consecutive verses.
     */
    public function test_compress_verse_numbers_consecutive(): void
    {
        $method = new ReflectionMethod($this->controller, 'compressVerseNumbers');

        $result = $method->invoke($this->controller, [2, 3, 4]);

        $this->assertEquals('2-4', $result);
    }

    /**
     * Test compressVerseNumbers with non-consecutive verses.
     */
    public function test_compress_verse_numbers_non_consecutive(): void
    {
        $method = new ReflectionMethod($this->controller, 'compressVerseNumbers');

        $result = $method->invoke($this->controller, [2, 4, 5]);

        $this->assertEquals('2.4-5', $result);
    }
}
