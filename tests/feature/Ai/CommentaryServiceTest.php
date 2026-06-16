<?php

namespace SzentirasHu\Test\Ai;

use SzentirasHu\Models\Commentary;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Test\Common\TestCase;

class CommentaryServiceTest extends TestCase
{
    private function service(): CommentaryService
    {
        return app(CommentaryService::class);
    }

    public function test_parse_commentary_collection_shapes_data(): void
    {
        $commentary = new Commentary();
        $commentary->commentary_text = json_encode(['commentary_text' => 'Hello', 'references' => []]);
        $commentary->is_exact = true;
        $commentary->status = 'completed';
        $commentary->verification_level = 'none';
        $commentary->id = 7;
        $commentary->metadata = ['reference' => 'Jn 3,16'];

        $parsed = $this->service()->parseCommentaryCollection(collect([$commentary]));

        $this->assertCount(1, $parsed);
        $this->assertSame('Hello', $parsed[0]['commentary_text']);
        $this->assertTrue($parsed[0]['exact']);
        $this->assertSame('completed', $parsed[0]['status']);
        $this->assertSame(7, $parsed[0]['commentary_id']);
        $this->assertSame('Jn 3,16', $parsed[0]['commentary_reference']);
    }

    public function test_parse_handles_plain_text_commentary(): void
    {
        $commentary = new Commentary();
        $commentary->commentary_text = 'Just plain text';
        $commentary->is_exact = false;
        $commentary->status = 'completed';
        $commentary->verification_level = 'none';
        $commentary->id = 1;
        $commentary->metadata = [];

        $parsed = $this->service()->parseCommentaryCollection(collect([$commentary]));

        $this->assertSame('Just plain text', $parsed[0]['commentary_text']);
        $this->assertSame([], $parsed[0]['references']);
    }

    public function test_generation_gating_when_disabled(): void
    {
        config(['ai.configurations.commentary.all_users_allowed' => false]);
        $service = $this->service();

        $this->assertFalse($service->commentaryGenerationPossible());
        $this->assertFalse($service->canGenerateCommentary(false));
        // Editors may always generate, regardless of the all-users setting.
        $this->assertTrue($service->canGenerateCommentary(true));
    }
}
