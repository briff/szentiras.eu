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
use SzentirasHu\Models\Commentary;

class ProcessOpenAIBatchOutput implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $openaiBatchId) {}

    public function handle(OpenAIClient $openai)
    {
        $batch = OpenAIBatch::findOrFail($this->openaiBatchId);

        $jsonl = $openai->files()->download($batch->output_file_id);

        foreach (explode("\n", trim($jsonl)) as $line) {
            $row = json_decode($line, true);
            if (!$row) continue;

            $customId = $row['custom_id'] ?? null;
            if (!$customId) continue;

            $item = $batch->items()->where('custom_id', $customId)->first();
            if (!$item) continue;

            [$text, $tokenUsage, $error] = $this->extractTextAndTokensFromBatchRow($row);

            $item->status = $text ? 'succeeded' : 'failed';
            // Note: result_text column removed per user request
            $item->error = $text ? null : json_encode($row['response'] ?? $row);
            $item->save();

            // Optionally: write into your domain table here
            if ($text && $item->source_id) {
                $this->updateCommentaryFromBatchItem($item, $text, $tokenUsage);
            }
        }

        $batch->status = 'processed';
        $batch->save();
    }

    /**
     * Update commentary with generated text from batch item.
     *
     * @param \SzentirasHu\Models\OpenAIBatchItem $item
     * @param string $text
     * @param int|null $tokenUsage
     * @return void
     */
    private function updateCommentaryFromBatchItem($item, string $text, ?int $tokenUsage): void
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

        $resp = $row['response'] ?? null;
        if (!is_array($resp)) {
            return ['', null, ['message' => 'Missing response object']];
        }

        $text = $resp['output'][0]['content'][0]['text'] ?? '';

        $usage = $resp['usage'] ?? [];
        $tokens = $usage['total_tokens'] ?? $usage['totalTokens'] ?? null;

        return [$text, $tokens, null];
    }

}