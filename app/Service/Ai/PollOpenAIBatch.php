<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        Log::debug('PollOpenAIBatch: starting', ['batch_id' => $this->openaiBatchId]);
        
        $batch = OpenAIBatch::findOrFail($this->openaiBatchId);

        if (!$batch->batch_id) {
            Log::debug('PollOpenAIBatch: no OpenAI batch_id yet, skipping', ['batch_id' => $this->openaiBatchId]);
            return;
        }

        Log::debug('PollOpenAIBatch: retrieving remote batch', ['openai_batch_id' => $batch->batch_id]);
        $remote = $openai->batches()->retrieve($batch->batch_id);

        $oldStatus = $batch->status;
        $batch->status = $remote['status'] ?? $batch->status;
        $batch->output_file_id = $remote['output_file_id'] ?? null;
        $batch->error_file_id  = $remote['error_file_id'] ?? null;
        $batch->save();

        if ($oldStatus !== $batch->status) {
            Log::debug('PollOpenAIBatch: status changed', [
                'batch_id' => $batch->id,
                'old_status' => $oldStatus,
                'new_status' => $batch->status,
                'output_file_id' => $batch->output_file_id,
                'error_file_id' => $batch->error_file_id,
            ]);
        } else {
            Log::debug('PollOpenAIBatch: status unchanged', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
                'output_file_id' => $batch->output_file_id,
                'error_file_id' => $batch->error_file_id,
            ]);
        }

        // Terminal success
        if ($batch->status === 'completed' && $batch->output_file_id) {
            Log::debug('PollOpenAIBatch: batch completed, dispatching ProcessOpenAIBatchOutput', [
                'batch_id' => $batch->id,
                'output_file_id' => $batch->output_file_id,
            ]);
            ProcessOpenAIBatchOutput::dispatch($batch->id)->onQueue('openai-batch');
            return;
        }

        // Terminal failure states
        if (in_array($batch->status, ['failed', 'expired', 'cancelled'], true)) {
            Log::debug('PollOpenAIBatch: batch failed, dispatching ProcessOpenAIBatchErrors', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
            ]);
            ProcessOpenAIBatchErrors::dispatch($batch->id)->onQueue('openai-batch');
            return;
        }

        // Not ready yet: requeue with backoff
        $delay = match (true) {
            $this->attempts() < 5  => 60,    // 1 min
            $this->attempts() < 15 => 180,   // 3 min
            default                => 600,   // 10 min
        };

        Log::debug('PollOpenAIBatch: requeuing with delay', [
            'batch_id' => $batch->id,
            'attempts' => $this->attempts(),
            'delay_seconds' => $delay,
            'status' => $batch->status,
        ]);
        
        self::dispatch($batch->id)->delay(now()->addSeconds($delay))->onQueue('openai-batch');
    }
}
