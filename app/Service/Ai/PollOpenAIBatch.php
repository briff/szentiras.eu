<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use OpenAI\Client as OpenAIClient;
use SzentirasHu\Models\OpenAIBatch;

class PollOpenAIBatch implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 50; // enough for a day with backoff

    // Optional: how long it stays unique (seconds)
    public $uniqueFor = 900; // 15 minutes

    public function __construct(public int $openaiBatchId) {}

    public function uniqueId(): string
    {
        return (string) $this->openaiBatchId;
    }

    public function handle(OpenAIClient $openai)
    {
        $batch = OpenAIBatch::findOrFail($this->openaiBatchId);

        if (!$batch->batch_id) {
            // nothing to poll yet
            return;
        }

        $remote = $openai->batches()->retrieve($batch->batch_id);

        $batch->status = $remote['status'] ?? $batch->status;
        $batch->output_file_id = $remote['output_file_id'] ?? null;
        $batch->error_file_id  = $remote['error_file_id'] ?? null;
        $batch->save();

        // Terminal success
        if ($batch->status === 'completed' && $batch->output_file_id) {
            ProcessOpenAIBatchOutput::dispatch($batch->id)->onQueue('openai-batch');
            return;
        }

        // Terminal failure states
        if (in_array($batch->status, ['failed', 'expired', 'cancelled'], true)) {
            if ($batch->error_file_id) {
                ProcessOpenAIBatchErrors::dispatch($batch->id)->onQueue('openai-batch');
            }
            return;
        }

        // Not ready yet: requeue with backoff
        $delay = match (true) {
            $this->attempts() < 5  => 60,    // 1 min
            $this->attempts() < 15 => 180,   // 3 min
            default                => 600,   // 10 min
        };

        self::dispatch($batch->id)->delay(now()->addSeconds($delay))->onQueue('openai-batch');
    }
}
