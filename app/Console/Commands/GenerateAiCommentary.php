<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\TextService;

class GenerateAiCommentary extends Command
{
    protected $signature = 'ai:generate-commentary
                            {reference : Bible reference (e.g., "MAT_1_2-MAT_1_6,MAT_1_12,MAT_1_23-MAT_2_5")}
                            {translation : Translation abbreviation (e.g., "KNB")}
                            {--dry-run : Generate commentary but do not save to database}
                            {--force : Overwrite existing commentary for the same reference}
                            {--metadata= : JSON metadata to attach (e.g., \'{"model":"gpt-4","temperature":0.7}\')}';

    protected $description = 'Generate AI commentary for a Bible reference and store it.';

    public function __construct(
        private readonly ReferenceService $referenceService,
        private readonly TextService $textService,
        private readonly AiPromptService $aiPromptService,
        private readonly CommentaryService $commentaryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $referenceString = $this->argument('reference');
        $translationAbbrev = $this->argument('translation');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Parse translation
        $translation = Translation::where('abbrev', $translationAbbrev)->first();
        if (!$translation) {
            $this->error("Translation '{$translationAbbrev}' not found.");
            return self::FAILURE;
        }

        // Parse reference to get book (usx_code)
        $parts = explode('_', explode(',', $referenceString)[0]);
        $usxCode = $parts[0];

        // Check for existing commentary if not forcing
        if (!$force && !$dryRun) {
            $existing = $this->commentaryService->findForReference(
                CanonicalReference::fromString($referenceString),
                $translation
            );
            if ($existing->isNotEmpty()) {
                $this->warn("Commentary already exists for {$referenceString} in {$translationAbbrev}.");
                if (!$this->confirm('Overwrite?', false)) {
                    $this->info('Aborted.');
                    return self::SUCCESS;
                }
            }
        }

        // Parse ranges
        try {
            $ranges = $this->commentaryService->parseRangesFromReference($referenceString, $usxCode);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Generating commentary for {$referenceString} ({$translationAbbrev})...");

        // Generate canonical reference for AI prompt
        $canonicalRef = CanonicalReference::fromString($referenceString);

        // Generate commentary text
        $commentaryText = $this->commentaryService->generateCommentaryText(
            $canonicalRef,
            $translation,
            $this->aiPromptService,
            $this->getAdditionalPlaceholders()
        );

        if (empty($commentaryText)) {
            $this->error('Failed to generate commentary text.');
            return self::FAILURE;
        }

        $this->info("Commentary generated (" . strlen($commentaryText) . " characters).");

        if ($dryRun) {
            $this->info("Dry run - commentary text preview:");
            $this->line(substr($commentaryText, 0, 500) . (strlen($commentaryText) > 500 ? '...' : ''));
            $this->info("Ranges:");
            foreach ($ranges as $range) {
                $this->line("  {$range['start_chapter']}:{$range['start_verse']} - {$range['end_chapter']}:{$range['end_verse']}");
            }
            return self::SUCCESS;
        }

        // Prepare metadata
        $metadata = $this->getMetadata();

        // Store commentary
        $commentary = $this->commentaryService->store(
            $translation,
            $usxCode,
            $commentaryText,
            $ranges,
            $metadata
        );

        $this->info("Commentary saved with ID {$commentary->id}.");
        $this->info("Coverage: " . $commentary->ranges->map->toString()->implode(', '));

        return self::SUCCESS;
    }

    private function getAdditionalPlaceholders(): array
    {
        return [
            'command_timestamp' => now()->toIso8601String(),
            'user' => get_current_user() ?: 'system',
        ];
    }

    private function getMetadata(): array
    {
        $metadata = [];

        if ($this->option('metadata')) {
            $metadata = json_decode($this->option('metadata'), true) ?? [];
        }

        $defaults = [
            'generated_at' => now()->toIso8601String(),
            'command' => $this->getName(),
            'reference' => $this->argument('reference'),
            'translation' => $this->argument('translation'),
        ];

        return array_merge($defaults, $metadata);
    }
}