<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Service\Ai\GeneratesCommentary;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Jobs\GenerateCommentaryJob;

class GenerateAiCommentary extends Command
{
    use GeneratesCommentary;

    protected $signature = 'szentiras:generate-commentary
                            {reference : Bible reference (e.g., "Mt1,2-3.4-6")}
                            {translation : Translation abbreviation (e.g., "KNB")}
                            {--dry-run : Generate commentary but do not save to database}
                            {--force : Overwrite existing commentary for the same reference}
                            {--sync : Generate commentary synchronously (skip queue)}
                            {--batch : Use OpenAI batch API for asynchronous processing}
                            {--metadata= : JSON metadata to attach (e.g., \'{"model":"gpt-4","temperature":0.7}\')}';

    protected $description = 'Generate AI commentary for a Bible reference and store it.';

    public function __construct(
        private readonly ReferenceService $referenceService,
        private readonly TextService $textService,
        private readonly BookService $bookService,
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
        $sync = $this->option('sync');
        $batch = $this->option('batch');

        // Parse translation
        $translation = Translation::where('abbrev', $translationAbbrev)->first();
        if (!$translation) {
            $this->error("Translation '{$translationAbbrev}' not found.");
            return self::FAILURE;
        }

        // Parse reference to get book (usx_code)
        // Detect format and extract USX code
        $usxCode = $this->extractUsxCodeFromReference($referenceString, $translationAbbrev);

        // Parse ranges - convert to USX format if needed
        try {
            $usxReferenceString = $this->convertToUsxFormatIfNeeded($referenceString, $translationAbbrev);
            $ranges = $this->commentaryService->parseRangesFromReference($usxReferenceString, $usxCode);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // Check for existing commentary with exact same coverage if not forcing
        if (!$force && !$dryRun) {
            $existing = $this->findExistingCommentaryWithExactCoverage($translation, $usxCode, $ranges);
            if ($existing) {
                $this->warn("Commentary already exists for {$referenceString} in {$translationAbbrev}.");
                if (!$this->confirm('Overwrite?', false)) {
                    $this->info('Aborted.');
                    return self::SUCCESS;
                }
            }
        }

        $this->info("Generating commentary for {$referenceString} ({$translationAbbrev})...");

        // Determine if we should generate synchronously
        $generateSync = $sync || $dryRun;

        if ($generateSync) {
            // Generate canonical reference for AI prompt
            // If input is already canonical, use it directly; otherwise convert from USX
            $canonicalRefString =$referenceString;


            $result = $this->generateCommentaryForReference(
                $canonicalRefString,
                $translation,
                $this->commentaryService,
                $this->aiPromptService,
                $force
            );

            $commentaryText = $result['text'];
            $sourceText = $result['source_text'];
            $tokenUsage = $result['token_usage'];

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
                $metadata,
                $sourceText,
                $tokenUsage
            );

            $this->line('');
            $this->info("Commentary saved with ID {$commentary->id}.");
            $this->info("Token usage: {$tokenUsage}");
            $this->info("Coverage: " . $commentary->ranges->map->toString()->implode(', '));

            return self::SUCCESS;
        } else {
            // Asynchronous generation
            if ($batch) {
                // Batch mode: create pending commentary and submit to OpenAI batch API
                $this->info("Creating pending commentary and submitting to OpenAI batch API...");

                // Prepare metadata (includes reference)
                $metadata = $this->getMetadata();

                // Create pending commentary
                $commentary = $this->commentaryService->createPendingCommentary(
                    $translation,
                    $usxCode,
                    $ranges,
                    $metadata
                );

                // Create canonical reference for AI prompt
                // If input is already canonical, use it directly; otherwise convert from USX
                $canonicalRefString = $referenceString;
                
                $canonicalRef = \SzentirasHu\Service\Reference\CanonicalReference::fromString($canonicalRefString);




                // Generate commentary text using batch mode
                $maxLength = config('ai.configurations.commentary.max_input_length', 8000);
                $result = $this->commentaryService->generateCommentaryText(
                    $canonicalRef,
                    $translation,
                    $this->aiPromptService,
                    $maxLength,
                    $force,
                    true, // useBatch
                    $commentary->id // commentaryId
                );

                // Save source text to commentary for batch processing
                if (!empty($result['source_text'])) {
                    $commentary->source_text = $result['source_text'];
                    $commentary->save();
                }

                $this->info("Pending commentary created with ID {$commentary->id}. Batch job submitted.");
                $this->info("Coverage: " . $commentary->ranges->map->toString()->implode(', '));
                $this->info("The commentary will be generated via OpenAI batch API. Results will be processed when available.");
            } else {
                // Regular async mode: create pending commentary and dispatch job
                $this->info("Creating pending commentary and dispatching job...");

                // Prepare metadata (includes reference)
                $metadata = $this->getMetadata();

                // Create pending commentary
                $commentary = $this->commentaryService->createPendingCommentary(
                    $translation,
                    $usxCode,
                    $ranges,
                    $metadata
                );

                // Dispatch job
                GenerateCommentaryJob::dispatch($commentary);

                $this->info("Pending commentary created with ID {$commentary->id}. Job dispatched.");
                $this->info("Coverage: " . $commentary->ranges->map->toString()->implode(', '));
                $this->info("The commentary will be generated in the background. Use the queue worker to process jobs.");
            }

            return self::SUCCESS;
        }

    }

    /**
     * Find an existing commentary with the exact same coverage (ranges).
     *
     * @param Translation $translation
     * @param string $usxCode
     * @param array $ranges Array of ranges to match
     * @return Commentary|null
     */
    private function findExistingCommentaryWithExactCoverage(Translation $translation, string $usxCode, array $ranges): ?Commentary
    {
        // Get all commentaries for this book and translation
        $commentaries = Commentary::query()
            ->where('translation_id', $translation->id)
            ->where('usx_code', $usxCode)
            ->with('ranges')
            ->get();

        // Check each commentary to see if it has the exact same ranges
        foreach ($commentaries as $commentary) {
            if ($this->rangesMatch($commentary->ranges->toArray(), $ranges)) {
                return $commentary;
            }
        }

        return null;
    }

    /**
     * Check if two sets of ranges are identical.
     *
     * @param array $existingRanges
     * @param array $newRanges
     * @return bool
     */
    private function rangesMatch(array $existingRanges, array $newRanges): bool
    {
        if (count($existingRanges) !== count($newRanges)) {
            return false;
        }

        // Normalize ranges for comparison (remove database-specific fields)
        $normalizeRange = function (array $range): array {
            return [
                'start_chapter' => (int) $range['start_chapter'],
                'start_verse' => (int) $range['start_verse'],
                'end_chapter' => (int) $range['end_chapter'],
                'end_verse' => (int) $range['end_verse'],
            ];
        };

        $existingNormalized = array_map($normalizeRange, $existingRanges);
        $newNormalized = array_map($normalizeRange, $newRanges);

        // Sort both arrays for comparison
        usort($existingNormalized, fn ($a, $b) => [
            $a['start_chapter'],
            $a['start_verse'],
            $a['end_chapter'],
            $a['end_verse'],
        ] <=> [
            $b['start_chapter'],
            $b['start_verse'],
            $b['end_chapter'],
            $b['end_verse'],
        ]);

        usort($newNormalized, fn ($a, $b) => [
            $a['start_chapter'],
            $a['start_verse'],
            $a['end_chapter'],
            $a['end_verse'],
        ] <=> [
            $b['start_chapter'],
            $b['start_verse'],
            $b['end_chapter'],
            $b['end_verse'],
        ]);

        return $existingNormalized === $newNormalized;
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
            'force' => (bool) $this->option('force'),
            'max_length' => config('ai.configurations.commentary.max_input_length'),
            'model' => config('ai.configurations.commentary.model'),
        ];

        return array_merge($defaults, $metadata);
    }

    /**
     * Extract USX code from reference string (supports both USX and canonical formats).
     *
     * @param string $referenceString Reference in either USX or canonical format
     * @param string $translationAbbrev Translation abbreviation
     * @return string USX code
     */
    private function extractUsxCodeFromReference(string $referenceString, string $translationAbbrev): string
    {
        
        // Canonical format: parse with CanonicalReference
        try {
            $canonicalRef = \SzentirasHu\Service\Reference\CanonicalReference::fromString($referenceString);
            if (empty($canonicalRef->bookRefs)) {
                throw new \InvalidArgumentException("Could not parse reference: {$referenceString}");
            }
            
            $firstBookRef = $canonicalRef->bookRefs[0];
            $bookId = $firstBookRef->bookId;
            
            // Convert book abbreviation to USX code
            $usxCode = \SzentirasHu\Data\UsxCodes::getUsxFromBookAbbrevAndTranslation($bookId, $translationAbbrev);
            if (!$usxCode) {
                throw new \InvalidArgumentException("Could not find USX code for book: {$bookId} in translation: {$translationAbbrev}");
            }
            
            return $usxCode;
        } catch (\SzentirasHu\Service\Reference\ParsingException $e) {
            throw new \InvalidArgumentException("Invalid reference format: {$referenceString}. Expected canonical (e.g., Mt5,20-26) or USX (e.g., MAT_5_20-MAT_5_26) format.");
        }
    }

    /**
     * Convert canonical format reference to USX format if needed.
     *
     * @param string $referenceString Reference in either format
     * @param string $translationAbbrev Translation abbreviation
     * @return string Reference in USX format
     */
    private function convertToUsxFormatIfNeeded(string $referenceString, string $translationAbbrev): string
    {
        
        // Parse canonical reference
        $canonicalRef = \SzentirasHu\Service\Reference\CanonicalReference::fromString($referenceString);
        $usxParts = [];
        
        // Get translation object for verse count lookup
        $translation = \SzentirasHu\Data\Entity\Translation::where('abbrev', $translationAbbrev)->first();
        if (!$translation) {
            throw new \InvalidArgumentException("Translation '{$translationAbbrev}' not found.");
        }
        
        foreach ($canonicalRef->bookRefs as $bookRef) {
            $bookUsxCode = \SzentirasHu\Data\UsxCodes::getUsxFromBookAbbrevAndTranslation($bookRef->bookId, $translationAbbrev);
            if (!$bookUsxCode) {
                throw new \InvalidArgumentException("Could not find USX code for book: {$bookRef->bookId}");
            }
            
            // If no chapter ranges specified (book-level reference), treat as full book
            if (empty($bookRef->chapterRanges)) {
                // For a full book reference, we'll use chapter 1, verse 1 to end
                // This will be handled by the parseRangesFromReference method
                $usxParts[] = "{$bookUsxCode}_1_1";
                continue;
            }
            
            foreach ($bookRef->chapterRanges as $chapterRange) {
                $chapterRef = $chapterRange->chapterRef;
                $chapterId = $chapterRef->chapterId;
                
                // If no verse ranges specified (chapter-level reference), use full chapter
                if (empty($chapterRef->verseRanges)) {
                    // Get the last verse of this chapter
                    $book = $this->bookService->getBookByUsxCodeTranslation($bookUsxCode, $translationAbbrev);
                    $lastVerse = $this->bookService->getVerseCount($book, (int) $chapterId, $translation);
                    $usxParts[] = "{$bookUsxCode}_{$chapterId}_1-{$bookUsxCode}_{$chapterId}_{$lastVerse}";
                    continue;
                }
                
                foreach ($chapterRef->verseRanges as $verseRange) {
                    $startVerse = $verseRange->verseRef ? $verseRange->verseRef->verseId : 1;
                    $endVerse = $verseRange->untilVerseRef ? $verseRange->untilVerseRef->verseId : $startVerse;
                    
                    if ($startVerse === $endVerse) {
                        // Single verse
                        $usxParts[] = "{$bookUsxCode}_{$chapterId}_{$startVerse}";
                    } else {
                        // Verse range
                        $usxParts[] = "{$bookUsxCode}_{$chapterId}_{$startVerse}-{$bookUsxCode}_{$chapterId}_{$endVerse}";
                    }
                }
            }
        }
        
        return implode(',', $usxParts);
    }
}