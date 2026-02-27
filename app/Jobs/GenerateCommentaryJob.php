<?php

namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Ai\GeneratesCommentary;
use Illuminate\Support\Facades\Log;

class GenerateCommentaryJob extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels, GeneratesCommentary;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The backoff strategy for retries (seconds).
     *
     * @var array
     */
    public $backoff = [60, 120];

    /**
     * The commentary instance.
     *
     * @var Commentary
     */
    protected $commentary;

    /**
     * Create a new job instance.
     *
     * @param Commentary $commentary
     * @return void
     */
    public function __construct(Commentary $commentary)
    {
        $this->commentary = $commentary;
    }

    /**
     * Execute the job.
     *
     * @param CommentaryService $commentaryService
     * @param AiPromptService $aiPromptService
     * @return void
     */
    public function handle(CommentaryService $commentaryService, AiPromptService $aiPromptService): void
    {
        // If commentary is already completed, nothing to do.
        if ($this->commentary->status === Commentary::STATUS_COMPLETED) {
            Log::info("Commentary {$this->commentary->id} already completed, skipping.");
            return;
        }

        // Update job_id with this job's ID (if available)
        if ($this->job && method_exists($this->job, 'getJobId')) {
            $this->commentary->job_id = $this->job->getJobId();
        }

        // Move to processing status
        $this->commentary->status = Commentary::STATUS_PROCESSING;
        $this->commentary->started_at = now();
        $this->commentary->save();

        try {
            // Retrieve reference from metadata (stored during pending creation)
            $metadata = $this->commentary->metadata ?? [];
            $referenceString = $metadata['reference'] ?? '';
            $force = $metadata['force'] ?? false;

            $result = $this->generateCommentaryForReference(
                $referenceString,
                $this->commentary->translation,
                $commentaryService,
                $aiPromptService,
                $force
            );

            $this->handleCommentarySuccess(
                $this->commentary,
                $result['text'],
                $result['source_text'],
                $result['token_usage']
            );
        } catch (\Throwable $e) {
            $this->handleCommentaryFailure($this->commentary, $e);

            // Re-throw to allow job retry (if attempts left)
            throw $e;
        }
    }

    /**

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // If all attempts exhausted, mark as failed (already done in catch)
        // This method is called after all retries have been exhausted.
        // Ensure status is failed (might already be set).
        if ($this->commentary->status !== Commentary::STATUS_FAILED) {
            $this->commentary->status = Commentary::STATUS_FAILED;
            $this->commentary->error_message = $exception->getMessage();
            $this->commentary->save();
        }

        Log::error("GenerateCommentaryJob failed for commentary {$this->commentary->id} after all attempts.", [
            'exception' => $exception,
        ]);
    }
}