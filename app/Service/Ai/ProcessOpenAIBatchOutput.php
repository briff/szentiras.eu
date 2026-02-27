<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use OpenAI\Client as OpenAIClient;
use OpenAI\Factory as OpenAIFactory;
use SzentirasHu\Models\OpenAIBatch;
use SzentirasHu\Models\OpenAIBatchItem;
use SzentirasHu\Models\Commentary;

class ProcessOpenAIBatchOutput implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $openaiBatchId) {}

    public function handle(AiPromptService $aiPromptService)
    {
        Log::debug("ProcessOpenAIBatchOutput: Starting batch processing for batch ID: {$this->openaiBatchId}");
        $openai = $aiPromptService->clientForConfig('commentary');
        $batch = OpenAIBatch::findOrFail($this->openaiBatchId);
        Log::debug("ProcessOpenAIBatchOutput: Found batch", [
            'batch_id' => $batch->id,
            'output_file_id' => $batch->output_file_id,
            'status' => $batch->status,
        ]);

        $jsonl = $openai->files()->download($batch->output_file_id);
        Log::debug("ProcessOpenAIBatchOutput: Downloaded output file", [
            'file_size' => strlen($jsonl),
            'lines_count' => count(explode("\n", trim($jsonl))),
        ]);

        $processedCount = 0;
        $successCount = 0;
        $failureCount = 0;

        foreach (explode("\n", trim($jsonl)) as $line) {
            $row = json_decode($line, true);
            if (!$row) {
                Log::debug("ProcessOpenAIBatchOutput: Skipping invalid JSON line");
                continue;
            }

            $customId = $row['custom_id'] ?? null;
            if (!$customId) {
                Log::debug("ProcessOpenAIBatchOutput: Skipping row without custom_id");
                continue;
            }

            /** @var OpenAIBatchItem|null $item */
            $item = $batch->items()->where('custom_id', $customId)->first();
            if (!$item instanceof OpenAIBatchItem) {
                Log::warning("ProcessOpenAIBatchOutput: Batch item not found for custom_id: {$customId}");
                continue;
            }

            [$text, $tokenUsage, $error] = $this->extractTextAndTokensFromBatchRow($row);

            $item->status = $text ? 'succeeded' : 'failed';
            // Note: result_text column removed per user request
            $item->error = $text ? null : json_encode($row['response'] ?? $row);
            $item->save();

            $processedCount++;
            if ($text) {
                $successCount++;
                Log::debug("ProcessOpenAIBatchOutput: Batch item succeeded", [
                    'item_id' => $item->id,
                    'custom_id' => $customId,
                    'token_usage' => $tokenUsage,
                ]);
            } else {
                $failureCount++;
                Log::warning("ProcessOpenAIBatchOutput: Batch item failed", [
                    'item_id' => $item->id,
                    'custom_id' => $customId,
                    'error' => $item->error,
                ]);
            }

            // Optionally: write into your domain table here
            if ($text && $item->source_id) {
                $this->updateCommentaryFromBatchItem($item, $text, $tokenUsage);
            }
        }

        $batch->status = 'processed';
        $batch->save();

        Log::info("ProcessOpenAIBatchOutput: Batch processing completed", [
            'batch_id' => $batch->id,
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }

    /**
     * Update commentary with generated text from batch item.
     *
     * @param \SzentirasHu\Models\OpenAIBatchItem $item
     * @param string $text
     * @param int|null $tokenUsage
     * @return void
     */
    private function updateCommentaryFromBatchItem(OpenAIBatchItem $item, string $text, ?int $tokenUsage): void
    {
        try {
            $commentary = Commentary::find($item->source_id);
            if (!$commentary) {
                Log::warning("Commentary not found for source_id: {$item->source_id} in batch item {$item->id}");
                return;
            }

            // Update commentary with generated text
            $commentary->commentary_text = $text;
            $commentary->token_usage = $tokenUsage;
            $commentary->status = Commentary::STATUS_COMPLETED;
            $commentary->completed_at = now();

            // If not already started, set started_at
            if (!$commentary->started_at) {
                $commentary->started_at = now();
            }

            $commentary->save();

            Log::info("Commentary {$commentary->id} updated from batch item {$item->id} with token usage: {$tokenUsage}");
        } catch (\Exception $e) {
            Log::error("Failed to update commentary from batch item {$item->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }

    private function extractTextAndTokensFromBatchRow(array $row): array
    {
        if (isset($row['error'])) {
            return ['', null, $row['error']];
        }

        $body = $row['response']['body'] ?? null;
        if (!is_array($body)) {
            return ['', null, ['message' => 'Missing body object in response']];
        }

        $text = $body['output'][0]['content'][0]['text'] ?? '';
        $tokens = $body['usage']['total_tokens'] ?? null;

        return [$text, $tokens, null];
    }
}
