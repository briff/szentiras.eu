<?php

namespace SzentirasHu\Test\Ai;

use SzentirasHu\Test\Common\TestCase;

class AiToolPopoverViewTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function viewData(array $overrides = []): array
    {
        return array_merge([
            'pureTexts' => [
                ['translationAbbrev' => 'GNT', 'reference' => 'Mt 1,1', 'text' => 'Βίβλος γενέσεως', 'similarity' => null, 'greekSimilarity' => null],
            ],
            'similarsOT' => [],
            'similarsNT' => [],
            'greekText' => [
                ['usx_code' => 'MAT', 'chapter' => 1, 'verse' => 1, 'i' => 0, 'strong' => '976', 'translit' => 'biblos', 'printed' => 'Βίβλος'],
            ],
            'greekSimilarity' => null,
            'gepi' => 'MAT_1_1',
        ], $overrides);
    }

    public function test_word_translation_button_shown_for_greek_text(): void
    {
        $html = view('ai.aiToolPopover', $this->viewData())->render();

        $this->assertStringContainsString('Szavankénti fordítás', $html);
        $this->assertStringContainsString('toggle-word-translation', $html);
    }

    public function test_word_translation_button_hidden_for_other_translations(): void
    {
        $html = view('ai.aiToolPopover', $this->viewData([
            'pureTexts' => [
                ['translationAbbrev' => 'SZIT', 'reference' => 'Mt 1,1', 'text' => 'Jézus Krisztus nemzetségtáblája', 'similarity' => null, 'greekSimilarity' => null],
            ],
        ]))->render();

        $this->assertStringNotContainsString('Szavankénti fordítás', $html);
        $this->assertStringNotContainsString('toggle-word-translation', $html);
    }
}
