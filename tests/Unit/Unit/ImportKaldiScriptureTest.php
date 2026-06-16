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

    public function test_chapter_argument_is_dropped_and_no_verse_zero_is_created(): void
    {
        $html = <<<'HTML'
        <p class="fejezet"><a name="62:002"></a>2. fejezet</p>
        <p class="MsoNormal">A bűnről és bűnbocsánatról szóló bevezető összefoglalás.</p>
        <p class="vers"><a name="62:002.001"></a>1.</p>
        <p class="MsoNormal">Fiacskáim! ezeket írom nektek.</p>
        <p class="vers"><a name="62:002.002"></a>2.</p>
        <p class="MsoNormal">Ő engesztelő a mi bűneinkért.</p>
        HTML;

        $method = new ReflectionMethod(ImportKaldiScripture::class, 'parseVersesFromHtml');
        $rows = $method->invoke(new ImportKaldiScripture(), $html, '1jn');

        foreach ($rows as $row) {
            $this->assertNotSame(0, $row['numv'], 'No phantom verse 0 should be created');
            $this->assertStringNotContainsString('bevezető összefoglalás', $row['verse'], 'The chapter argument must be dropped');
        }

        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows[0]['chapter']);
        $this->assertSame(1, $rows[0]['numv']);
        $this->assertSame(901, $rows[0]['tip']);
        $this->assertSame('Fiacskáim! ezeket írom nektek.', $rows[0]['verse']);
        $this->assertSame(2, $rows[1]['numv']);
        $this->assertSame('Ő engesztelő a mi bűneinkért.', $rows[1]['verse']);
    }
}
