<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use OpenAI\Client as OpenAIClient;
use SzentirasHu\Models\OpenAIBatch;
use SzentirasHu\Models\OpenAIBatchItem;
use SzentirasHu\Models\Commentary;

class ProcessOpenAIBatchErrors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $openaiBatchId) {}

    public function handle(AiPromptService $aiPromptService)
    {
        $openai = $aiPromptService->clientForConfig('commentary');
        $batch = OpenAIBatch::findOrFail($this->openaiBatchId);

        if ($batch->error_file_id) {
            $jsonl = $openai->files()->download($batch->error_file_id);

            // Track which items we've processed from the error file
            $processedItemIds = [];

            foreach (explode("\n", trim($jsonl)) as $line) {
                $row = json_decode($line, true);
                if (!$row) continue;

                $customId = $row['custom_id'] ?? null;
                if (!$customId) continue;

                /** @var OpenAIBatchItem|null $item */
                $item = $batch->items()->where('custom_id', $customId)->first();
                if (!$item) continue;

                $errorDetails = $this->extractErrorFromBatchRow($row);

                $item->status = 'failed';
                $item->error = json_encode($errorDetails);
                $item->save();

                $processedItemIds[] = $item->id;

                // Update corresponding commentary to failed state
                if ($item->source_id) {
                    $this->updateCommentaryFromFailedBatchItem($item, $errorDetails);
                }
            }

            // Mark any remaining items as failed with a generic batch error
            $remainingItems = $batch->items()->whereNotIn('id', $processedItemIds)->get();
            /** @var OpenAIBatchItem $item */
            foreach ($remainingItems as $item) {
                if ($item->status !== 'failed') { // Only update if not already failed
                    $item->status = 'failed';
                    $item->error = json_encode([
                        'message' => 'Batch failed overall',
                        'batch_status' => $batch->status,
                        'details' => 'Item not present in error file, batch marked as failed overall'
                    ]);
                    $item->save();

                    if ($item->source_id) {
                        $this->updateCommentaryFromFailedBatchItem($item, [
                            'message' => 'Batch failed overall',
                            'batch_status' => $batch->status,
                        ]);
                    }
                }
            }
        } else {
            // No error file - mark all items as failed with generic error
            Log::warning("Batch {$batch->id} has no error file, marking all items as failed");
            
            /** @var OpenAIBatchItem $item */
            foreach ($batch->items as $item) {
                if ($item->status !== 'failed') {
                    $item->status = 'failed';
                    $item->error = json_encode([
                        'message' => 'Batch failed with no error details',
                        'batch_status' => $batch->status,
                    ]);
                    $item->save();

                    if ($item->source_id) {
                        $this->updateCommentaryFromFailedBatchItem($item, [
                            'message' => 'Batch failed with no error details',
                            'batch_status' => $batch->status,
                        ]);
                    }
                }
            }
        }

        $batch->status = 'error_processed';
        $batch->save();
    }

    /**
     * Update commentary with error from failed batch item.
     *
     * @param \SzentirasHu\Models\OpenAIBatchItem $item
     * @param array $errorDetails
     * @return void
     */
    private function updateCommentaryFromFailedBatchItem($item, array $errorDetails): void
    {
        try {
            $commentary = Commentary::find($item->source_id);
            if (!$commentary) {
                Log::warning("Commentary not found for source_id: {$item->source_id} in batch item {$item->id}");
                return;
            }

            // Update commentary with error
            $commentary->status = Commentary::STATUS_FAILED;
            $commentary->error_message = $this->formatErrorMessage($errorDetails);
            $commentary->completed_at = now();
            
            // If not already started, set started_at
            if (!$commentary->started_at) {
                $commentary->started_at = now();
            }

            $commentary->save();

            Log::info("Commentary {$commentary->id} marked as failed from batch item {$item->id} with error");
        } catch (\Exception $e) {
            Log::error("Failed to update commentary from batch item {$item->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Extract error details from batch row.
     *
     * @param array $row
     * @return array
     */
    private function extractErrorFromBatchRow(array $row): array
    {
        // OpenAI error format may vary; try common patterns
        if (isset($row['error'])) {
            return $row['error'];
        }

        if (isset($row['response']['error'])) {
            return $row['response']['error'];
        }

        // Fallback: include the whole row for debugging
        return [
            'message' => 'Unknown error format',
            'raw' => $row,
        ];
    }

    /**
     * Format error message for storage in commentary.
     *
     * @param array $errorDetails
     * @return string|null
     */
    private function formatErrorMessage(array $errorDetails): ?string
    {
        if (isset($errorDetails['message'])) {
            return $errorDetails['message'];
        }

        if (isset($errorDetails['type'])) {
            $type = $errorDetails['type'];
            $message = $errorDetails['message'] ?? 'No message';
            return "{$type}: {$message}";
        }

        // Return JSON representation if we can't extract a simple message
        return json_encode($errorDetails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}