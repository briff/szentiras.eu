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

class SubmitOpenAIBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $openaiBatchId) {}

    public function handle(AiPromptService $aiPromptService)
    {
        Log::debug('SubmitOpenAIBatch job started', ['batch_id' => $this->openaiBatchId]);
        $openai = $aiPromptService->clientForConfig('commentary');
        try {
            $batch = OpenAIBatch::findOrFail($this->openaiBatchId);
            Log::debug('Batch retrieved from database', ['batch_id' => $batch->id, 'status' => $batch->status]);

            // 1) Create JSONL locally
            $path = storage_path("app/batches/{$batch->id}.jsonl");
            @mkdir(dirname($path), 0775, true);

            $itemCount = 0;
            $fh = fopen($path, 'w');
            foreach ($batch->items()->cursor() as $item) {
                $line = [
                    'custom_id' => $item->custom_id,
                    'method' => 'POST',
                    'url' => '/v1/responses',
                    'body' => $item->payload, // store payload JSON per item
                ];
                fwrite($fh, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");
                $itemCount++;
            }
            fclose($fh);
            
            Log::debug('JSONL file created', [
                'batch_id' => $batch->id,
                'path' => $path,
                'item_count' => $itemCount
            ]);

            // 2) Upload file
            if (!$batch->input_file_id) {
                Log::debug('Uploading file to OpenAI', ['batch_id' => $batch->id, 'path' => $path]);
                $createResponse = $openai->files()->upload([
                    'file' => fopen($path, 'r'),
                    'purpose' => 'batch'
                ]);
                $batch->input_file_id = $createResponse->id;
                $batch->status = 'uploaded';
                $batch->save();
                Log::debug('File uploaded to OpenAI', [
                    'batch_id' => $batch->id,
                    'input_file_id' => $batch->input_file_id,
                    'status' => $batch->status
                ]);
            } else {
                Log::debug('File already uploaded to OpenAI', [
                    'batch_id' => $batch->id,
                    'input_file_id' => $batch->input_file_id
                ]);
            }

            // 3) Start batch
            if (!$batch->batch_id) {
                Log::debug('Creating OpenAI batch', [
                    'batch_id' => $batch->id,
                    'input_file_id' => $batch->input_file_id,
                    'model' => config('ai.configurations.commentary.model')
                ]);
                $batchResponse = $openai->batches()->create([
                    'input_file_id' => $batch->input_file_id,
                    'completion_window' => '24h',
                    'endpoint' => '/v1/responses',
                ]);
                $batch->batch_id = $batchResponse->id;
                $batch->status = $batchResponse->status;
                $batch->save();
                Log::debug('OpenAI batch created', [
                    'batch_id' => $batch->id,
                    'openai_batch_id' => $batch->batch_id,
                    'status' => $batch->status
                ]);
            } else {
                Log::debug('OpenAI batch already created', [
                    'batch_id' => $batch->id,
                    'openai_batch_id' => $batch->batch_id
                ]);
            }

            // 4) Start polling
            Log::debug('Dispatching PollOpenAIBatch job', [
                'batch_id' => $batch->id,
                'delay' => '2 minutes',
                'queue' => 'openai-batch'
            ]);
            PollOpenAIBatch::dispatch($batch->id)->delay(now()->addSeconds(30))->onQueue('openai-batch');
            
            Log::debug('SubmitOpenAIBatch job completed successfully', ['batch_id' => $batch->id]);
            
        } catch (\Exception $e) {
            Log::error('SubmitOpenAIBatch job failed', [
                'batch_id' => $this->openaiBatchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
