<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Models\CommentaryRange;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\TestCase;

class AiCommentaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommentaryService $service;
    private Translation $translation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CommentaryService::class);
        $this->translation = Translation::factory()->create(['abbrev' => 'KNB']);
    }

    /**
     * Hook called after the database is refreshed.
     * Reset PostgreSQL sequences to prevent ID collisions.
     */
    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    public function test_store_commentary_with_single_range(): void
    {
        $commentary = $this->service->store(
            $this->translation,
            'MAT',
            'This is a commentary on Matthew 1:1.',
            [
                ['start_chapter' => 1, 'start_verse' => 1, 'end_chapter' => 1, 'end_verse' => 1],
            ],
            ['model' => 'gpt-4']
        );

        $this->assertDatabaseHas('commentaries', [
            'id' => $commentary->id,
            'usx_code' => 'MAT',
            'translation_id' => $this->translation->id,
        ]);

        $this->assertDatabaseHas('commentary_ranges', [
            'commentary_id' => $commentary->id,
            'start_chapter' => 1,
            'start_verse' => 1,
            'end_chapter' => 1,
            'end_verse' => 1,
        ]);

        $this->assertCount(1, $commentary->ranges);
        $this->assertEquals('1:1', $commentary->ranges->first()->toString());
    }

    public function test_store_commentary_with_multiple_ranges(): void
    {
        $commentary = $this->service->store(
            $this->translation,
            'MAT',
            'Commentary covering multiple verses.',
            [
                ['start_chapter' => 1, 'start_verse' => 2, 'end_chapter' => 1, 'end_verse' => 6],
                ['start_chapter' => 1, 'start_verse' => 12, 'end_chapter' => 1, 'end_verse' => 12],
                ['start_chapter' => 1, 'start_verse' => 23, 'end_chapter' => 2, 'end_verse' => 5],
            ]
        );

        $this->assertCount(3, $commentary->ranges);
        $this->assertEquals('1:2-6', $commentary->ranges[0]->toString());
        $this->assertEquals('1:12', $commentary->ranges[1]->toString());
        $this->assertEquals('1:23-2:5', $commentary->ranges[2]->toString());
    }

    public function test_find_for_verse_within_range(): void
    {
        $commentary = $this->service->store(
            $this->translation,
            'MAT',
            'Test commentary',
            [
                ['start_chapter' => 1, 'start_verse' => 2, 'end_chapter' => 1, 'end_verse' => 6],
            ]
        );

        $results = $this->service->findForVerse('MAT', 1, 3, $this->translation);

        $this->assertCount(1, $results);
        $this->assertEquals($commentary->id, $results->first()->id);
    }

    public function test_find_for_verse_outside_range(): void
    {
        $this->service->store(
            $this->translation,
            'MAT',
            'Test commentary',
            [
                ['start_chapter' => 1, 'start_verse' => 2, 'end_chapter' => 1, 'end_verse' => 6],
            ]
        );

        $results = $this->service->findForVerse('MAT', 1, 7, $this->translation);

        $this->assertCount(0, $results);
    }

    public function test_find_for_verse_cross_chapter_range(): void
    {
        $commentary = $this->service->store(
            $this->translation,
            'MAT',
            'Cross chapter',
            [
                ['start_chapter' => 1, 'start_verse' => 23, 'end_chapter' => 2, 'end_verse' => 5],
            ]
        );

        // Verse in start chapter after start verse
        $results = $this->service->findForVerse('MAT', 1, 25, $this->translation);
        $this->assertCount(1, $results);
        $this->assertEquals($commentary->id, $results->first()->id);

        // Verse in end chapter before end verse
        $results = $this->service->findForVerse('MAT', 2, 3, $this->translation);
        $this->assertCount(1, $results);

        // Verse before start
        $results = $this->service->findForVerse('MAT', 1, 22, $this->translation);
        $this->assertCount(0, $results);

        // Verse after end
        $results = $this->service->findForVerse('MAT', 2, 6, $this->translation);
        $this->assertCount(0, $results);
    }

    public function test_find_for_reference(): void
    {
        $commentary = $this->service->store(
            $this->translation,
            'MAT',
            'Commentary',
            [
                ['start_chapter' => 1, 'start_verse' => 1, 'end_chapter' => 1, 'end_verse' => 10],
            ]
        );

        $reference = CanonicalReference::fromString('MAT 1,5-8');
        $results = $this->service->findForReference($reference, $this->translation);

        $this->assertCount(1, $results);
        $this->assertEquals($commentary->id, $results->first()->id);
    }

    public function test_parse_ranges_from_reference(): void
    {
        $ranges = $this->service->parseRangesFromReference(
            'MAT_1_2-MAT_1_6,MAT_1_12,MAT_1_23-MAT_2_5',
            'MAT'
        );

        $this->assertCount(3, $ranges);
        $this->assertEquals([
            'start_chapter' => 1,
            'start_verse' => 2,
            'end_chapter' => 1,
            'end_verse' => 6,
        ], $ranges[0]);
        $this->assertEquals([
            'start_chapter' => 1,
            'start_verse' => 12,
            'end_chapter' => 1,
            'end_verse' => 12,
        ], $ranges[1]);
        $this->assertEquals([
            'start_chapter' => 1,
            'start_verse' => 23,
            'end_chapter' => 2,
            'end_verse' => 5,
        ], $ranges[2]);
    }

    public function test_parse_ranges_from_reference_invalid_book(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Range must be within the same book: MAT');

        $this->service->parseRangesFromReference('MAT_1_2-MRK_1_6', 'MAT');
    }

    public function test_commentary_covers_verse_method(): void
    {
        $commentary = Commentary::create([
            'translation_id' => $this->translation->id,
            'usx_code' => 'MAT',
            'commentary_text' => 'Test',
        ]);

        $commentary->ranges()->create([
            'start_chapter' => 1,
            'start_verse' => 2,
            'end_chapter' => 1,
            'end_verse' => 6,
        ]);

        $commentary->load('ranges');

        $this->assertTrue($commentary->coversVerse(1, 3));
        $this->assertFalse($commentary->coversVerse(1, 1));
        $this->assertFalse($commentary->coversVerse(2, 1));
    }

    public function test_commentary_range_covers_verse_method(): void
    {
        $range = new CommentaryRange([
            'start_chapter' => 1,
            'start_verse' => 2,
            'end_chapter' => 1,
            'end_verse' => 6,
        ]);

        $this->assertTrue($range->coversVerse(1, 2));
        $this->assertTrue($range->coversVerse(1, 4));
        $this->assertTrue($range->coversVerse(1, 6));
        $this->assertFalse($range->coversVerse(1, 1));
        $this->assertFalse($range->coversVerse(1, 7));
        $this->assertFalse($range->coversVerse(2, 1));
    }

    public function test_commentary_range_cross_chapter_covers_verse(): void
    {
        $range = new CommentaryRange([
            'start_chapter' => 1,
            'start_verse' => 23,
            'end_chapter' => 2,
            'end_verse' => 5,
        ]);

        $this->assertTrue($range->coversVerse(1, 23));
        $this->assertTrue($range->coversVerse(1, 30));
        $this->assertTrue($range->coversVerse(2, 1));
        $this->assertTrue($range->coversVerse(2, 5));
        $this->assertFalse($range->coversVerse(1, 22));
        $this->assertFalse($range->coversVerse(2, 6));
        $this->assertFalse($range->coversVerse(3, 1));
    }

    public function test_create_pending_commentary(): void
    {
        $commentary = $this->service->createPendingCommentary(
            $this->translation,
            'MAT',
            [
                ['start_chapter' => 1, 'start_verse' => 1, 'end_chapter' => 1, 'end_verse' => 1],
            ],
            ['model' => 'gpt-4']
        );

        $this->assertDatabaseHas('commentaries', [
            'id' => $commentary->id,
            'status' => 'pending',
            'commentary_text' => null,
            'translation_id' => $this->translation->id,
            'usx_code' => 'MAT',
        ]);

        $this->assertCount(1, $commentary->ranges);
        $range = $commentary->ranges->first();
        $this->assertEquals(1, $range->start_chapter);
        $this->assertEquals(1, $range->start_verse);
        $this->assertEquals(1, $range->end_chapter);
        $this->assertEquals(1, $range->end_verse);
    }

    public function test_find_for_reference_includes_pending_commentary(): void
    {
        $pending = $this->service->createPendingCommentary(
            $this->translation,
            'MAT',
            [
                ['start_chapter' => 1, 'start_verse' => 1, 'end_chapter' => 1, 'end_verse' => 10],
            ]
        );

        $reference = CanonicalReference::fromString('MAT 1,5-8');
        $results = $this->service->findForReference($reference, $this->translation);

        $this->assertCount(1, $results);
        $found = $results->first();
        $this->assertEquals($pending->id, $found->id);
        $this->assertEquals(Commentary::STATUS_PENDING, $found->status);
        $this->assertNull($found->commentary_text);
    }

    public function test_find_for_reference_includes_processing_commentary(): void
    {
        $processing = Commentary::create([
            'translation_id' => $this->translation->id,
            'usx_code' => 'MAT',
            'commentary_text' => null,
            'status' => Commentary::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
        $processing->ranges()->create([
            'start_chapter' => 1,
            'start_verse' => 1,
            'end_chapter' => 1,
            'end_verse' => 10,
        ]);

        $reference = CanonicalReference::fromString('MAT 1,5-8');
        $results = $this->service->findForReference($reference, $this->translation);

        $this->assertCount(1, $results);
        $found = $results->first();
        $this->assertEquals($processing->id, $found->id);
        $this->assertEquals(Commentary::STATUS_PROCESSING, $found->status);
    }

    public function test_find_for_reference_returns_completed_and_pending_together(): void
    {
        $completed = $this->service->store(
            $this->translation,
            'MAT',
            'Completed commentary text',
            [
                ['start_chapter' => 1, 'start_verse' => 1, 'end_chapter' => 1, 'end_verse' => 5],
            ]
        );

        $pending = $this->service->createPendingCommentary(
            $this->translation,
            'MAT',
            [
                ['start_chapter' => 1, 'start_verse' => 3, 'end_chapter' => 1, 'end_verse' => 8],
            ]
        );

        $reference = CanonicalReference::fromString('MAT 1,4');
        $results = $this->service->findForReference($reference, $this->translation);

        $this->assertCount(2, $results);

        $statuses = $results->pluck('status')->toArray();
        $this->assertContains(Commentary::STATUS_COMPLETED, $statuses);
        $this->assertContains(Commentary::STATUS_PENDING, $statuses);
    }
}