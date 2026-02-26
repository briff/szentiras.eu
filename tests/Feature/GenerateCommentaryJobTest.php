<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use SzentirasHu\Jobs\GenerateCommentaryJob;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\TestCase;
use Mockery;

class GenerateCommentaryJobTest extends TestCase
{
    use RefreshDatabase;

    private Translation $translation;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_job_is_dispatched_when_command_called_without_sync(): void
    {
        Queue::fake();

        // Create a pending commentary
        $commentary = Commentary::create([
            'translation_id' => $this->translation->id,
            'usx_code' => 'MAT',
            'status' => 'pending',
            'commentary_text' => null,
        ]);

        // Dispatch the job
        GenerateCommentaryJob::dispatch($commentary);

        // Assert job was pushed to the queue
        Queue::assertPushed(GenerateCommentaryJob::class, function ($job) use ($commentary) {
            return $job->commentary->id === $commentary->id;
        });
    }

    public function test_job_handles_successfully_and_updates_commentary(): void
    {
        // Create a pending commentary
        $commentary = Commentary::create([
            'translation_id' => $this->translation->id,
            'usx_code' => 'MAT',
            'status' => 'pending',
            'commentary_text' => null,
        ]);

        // Mock CommentaryService
        $mockCommentaryService = Mockery::mock(CommentaryService::class);
        $mockAiPromptService = Mockery::mock(AiPromptService::class);

        // Expect generateCommentaryText to be called and return fake text
        $mockCommentaryService->shouldReceive('generateCommentaryText')
            ->once()
            ->with(
                Mockery::type(CanonicalReference::class),
                $this->translation,
                $mockAiPromptService,
                []
            )
            ->andReturn('Generated commentary text');

        // Swap the service instance in the container
        $this->app->instance(CommentaryService::class, $mockCommentaryService);
        $this->app->instance(AiPromptService::class, $mockAiPromptService);

        // Create job instance
        $job = new GenerateCommentaryJob($commentary);

        // Execute handle
        $job->handle($mockCommentaryService, $mockAiPromptService);

        // Refresh commentary from database
        $commentary->refresh();

        // Assert status updated to completed
        $this->assertEquals('completed', $commentary->status);
        $this->assertNotNull($commentary->commentary_text);
        $this->assertEquals('Generated commentary text', $commentary->commentary_text);
        $this->assertNotNull($commentary->started_at);
        $this->assertNotNull($commentary->completed_at);
    }

    public function test_job_handles_failure_and_updates_commentary(): void
    {
        $commentary = Commentary::create([
            'translation_id' => $this->translation->id,
            'usx_code' => 'MAT',
            'status' => 'pending',
            'commentary_text' => null,
        ]);

        $mockCommentaryService = Mockery::mock(CommentaryService::class);
        $mockAiPromptService = Mockery::mock(AiPromptService::class);

        // Simulate an exception during generation
        $mockCommentaryService->shouldReceive('generateCommentaryText')
            ->once()
            ->andThrow(new \RuntimeException('AI service unavailable'));

        $this->app->instance(CommentaryService::class, $mockCommentaryService);
        $this->app->instance(AiPromptService::class, $mockAiPromptService);

        $job = new GenerateCommentaryJob($commentary);

        try {
            $job->handle($mockCommentaryService, $mockAiPromptService);
        } catch (\RuntimeException $e) {
            // expected
        }

        // The failed method should have been called by Laravel's job handler
        // We'll manually call failed to test
        $job->failed(new \RuntimeException('AI service unavailable'));

        $commentary->refresh();
        $this->assertEquals('failed', $commentary->status);
        $this->assertStringContainsString('AI service unavailable', $commentary->error_message);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}