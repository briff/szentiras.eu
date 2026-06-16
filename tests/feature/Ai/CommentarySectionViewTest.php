<?php

namespace SzentirasHu\Test\Ai;

use SzentirasHu\Test\Common\TestCase;

class CommentarySectionViewTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function viewData(array $overrides = []): array
    {
        return array_merge([
            'containerCommentaries' => [],
            'containerReference' => '1Kir 9',
            'containerIndex' => 0,
            'translation' => (object) ['abbrev' => 'SZIT'],
            'canGenerateCommentary' => false,
            'commentaryGenerationPossible' => true,
            'isEditor' => false,
        ], $overrides);
    }

    public function test_info_panel_does_not_claim_feature_is_unavailable(): void
    {
        $html = view('textDisplay.commentarySection', $this->viewData())->render();

        $this->assertStringNotContainsString('fejlesztés alatt', $html);
        $this->assertStringNotContainsString('hamarosan elérhető', $html);
        $this->assertStringContainsString('Jelentkezz be', $html);
        $this->assertStringContainsString('/login', $html);
    }

    public function test_generate_button_shown_when_user_may_generate(): void
    {
        $html = view('textDisplay.commentarySection', $this->viewData([
            'canGenerateCommentary' => true,
        ]))->render();

        $this->assertStringContainsString('Kommentár készítése', $html);
        $this->assertStringContainsString(route('editor.commentaries.generate'), $html);
    }
}
