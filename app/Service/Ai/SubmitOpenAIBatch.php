<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use OpenAI\Client as OpenAIClient;
use OpenAI\Factory as OpenAIFactory;
use SzentirasHu\Models\OpenAIBatch;

class SubmitOpenAIBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $openaiBatchId) {}

    public function handle(OpenAIClient $openai)
    {
        $batch = OpenAIBatch::findOrFail($this->openaiBatchId);

        // 1) Create JSONL locally
        $path = storage_path("app/batches/{$batch->id}.jsonl");
        @mkdir(dirname($path), 0775, true);

        $fh = fopen($path, 'w');
        foreach ($batch->items()->cursor() as $item) {
            $line = [
                'custom_id' => $item->custom_id,
                'method' => 'POST',
                'url' => '/v1/responses',
                'body' => $item->payload, // store payload JSON per item
            ];
            fwrite($fh, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");
        }
        fclose($fh);

        // 2) Upload file
        if (!$batch->input_file_id) {
            $createResponse = $openai->files()->upload([
                'file' => fopen($path, 'r'),
                'purpose' => 'batch'
            ]);
            $batch->input_file_id = $createResponse->id;
            $batch->status = 'uploaded';
            $batch->save();
        }

        // 3) Start batch
        if (!$batch->batch_id) {
            $batchResponse = $openai->batches()->create([
                'input_file_id' => $batch->input_file_id,
                'model' => config('ai.configurations.commentary.model'),
                'purpose' => 'batch'
            ]);
            $batch->batch_id = $batchResponse->id;
            $batch->status = $batchResponse->status;
            $batch->save();
        }

        // 4) Start polling
        PollOpenAIBatch::dispatch($batch->id)->delay(now()->addMinutes(2))->onQueue('openai-batch');
    }
}
