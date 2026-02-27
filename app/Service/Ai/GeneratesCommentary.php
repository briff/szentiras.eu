<?php

namespace SzentirasHu\Service\Ai;

use SzentirasHu\Models\Commentary;
use SzentirasHu\Service\Reference\CanonicalReference;
use Illuminate\Support\Facades\Log;

trait GeneratesCommentary
{
    /**
     * Generate commentary text for a given reference.
     *
     * @param string $referenceString
     * @param mixed $translation
     * @param CommentaryService $commentaryService
     * @param AiPromptService $aiPromptService
     * @param bool $force
     * @return array
     * @throws \RuntimeException
     */
    protected function generateCommentaryForReference(
        string $referenceString,
        mixed $translation,
        CommentaryService $commentaryService,
        AiPromptService $aiPromptService,
        bool $force = false
    ): array {
        if (empty($referenceString)) {
            throw new \RuntimeException('Missing reference for commentary generation.');
        }

        if (!$translation) {
            throw new \RuntimeException('Translation not found for commentary.');
        }

        $canonicalRef = CanonicalReference::fromString($referenceString);

        // Generate commentary text using the service.
        $maxLength = config('ai.configurations.commentary.max_input_length', 8000);
        $result = $commentaryService->generateCommentaryText(
            $canonicalRef,
            $translation,
            $aiPromptService,
            $maxLength,
            $force
        );

        return [
            'text' => $result['text'],
            'source_text' => $result['source_text'],
            'token_usage' => $result['token_usage'],
        ];
    }

    /**
     * Handle successful commentary generation by updating the model.
     *
     * @param Commentary $commentary
     * @param string $commentaryText
     * @param string $sourceText
     * @param int $tokenUsage
     * @return void
     */
    protected function handleCommentarySuccess(
        Commentary $commentary,
        string $commentaryText,
        string $sourceText,
        int $tokenUsage
    ): void {
        $commentary->commentary_text = $commentaryText;
        $commentary->source_text = $sourceText;
        $commentary->token_usage = $tokenUsage;
        $commentary->status = Commentary::STATUS_COMPLETED;
        $commentary->completed_at = now();
        $commentary->save();

        Log::info("Commentary {$commentary->id} generated successfully. Token usage: {$tokenUsage}");
    }

    /**
     * Handle commentary generation failure.
     *
     * @param Commentary $commentary
     * @param \Throwable $exception
     * @return void
     */
    protected function handleCommentaryFailure(Commentary $commentary, \Throwable $exception): void
    {
        Log::error("Failed to generate commentary {$commentary->id}: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);

        $commentary->status = Commentary::STATUS_FAILED;
        $commentary->error_message = $exception->getMessage();
        $commentary->save();
    }
}
