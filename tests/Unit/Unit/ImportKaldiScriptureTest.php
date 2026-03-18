<?php

namespace Tests\Unit\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SzentirasHu\Console\Commands\ImportKaldiScripture;

class ImportKaldiScriptureTest extends TestCase
{
    public function test_parse_anchor_name_for_verse(): void
    {
        $method = new ReflectionMethod(ImportKaldiScripture::class, 'parseAnchorName');

        $result = $method->invoke(null, '47:005.032');

        $this->assertIsArray($result);
        $this->assertSame(47, $result['book_order']);
        $this->assertSame(5, $result['chapter']);
        $this->assertSame(32, $result['verse']);
    }

    public function test_normalize_html_text_removes_bracket_references(): void
    {
        $method = new ReflectionMethod(ImportKaldiScripture::class, 'normalizeHtmlText');

        $result = $method->invoke(null, 'Es monda [<span class=biblink>Mate 18,9</span>.] valamit.');

        $this->assertSame('Es monda valamit.', $result);
    }
}
