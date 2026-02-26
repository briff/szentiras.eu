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
        // Detect format and extract USX code
        $usxCode = $this->extractUsxCodeFromReference($referenceString, $translationAbbrev);

        // Check for existing commentary if not forcing
        if (!$force && !$dryRun) {
            // Convert USX format to canonical format for reference lookup
            // If input is already canonical, use it directly; otherwise convert from USX
            $canonicalRefString = str_contains($referenceString, '_')
                ? $this->convertUsxToCanonical($referenceString, $translationAbbrev)
                : $referenceString;
            $existing = $this->commentaryService->findForReference(
                CanonicalReference::fromString($canonicalRefString),
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

        // Parse ranges - convert to USX format if needed
        try {
            $usxReferenceString = $this->convertToUsxFormatIfNeeded($referenceString, $translationAbbrev);
            $ranges = $this->commentaryService->parseRangesFromReference($usxReferenceString, $usxCode);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Generating commentary for {$referenceString} ({$translationAbbrev})...");

        // Generate canonical reference for AI prompt
        // If input is already canonical, use it directly; otherwise convert from USX
        $canonicalRefString = str_contains($referenceString, '_')
            ? $this->convertUsxToCanonical($referenceString, $translationAbbrev)
            : $referenceString;
        $canonicalRef = CanonicalReference::fromString($canonicalRefString);

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

    /**
     * Convert USX format reference to canonical format.
     *
     * Example: "MAT_5_20-MAT_5_26" -> "Mat 5,20-26"
     * Example: "MAT_5_20,MAT_5_21" -> "Mat 5,20.21"
     *
     * @param string $usxReference USX format reference
     * @param string $translationAbbrev Translation abbreviation
     * @return string Canonical format reference
     */
    private function convertUsxToCanonical(string $usxReference, string $translationAbbrev): string
    {
        // Parse the USX reference into ranges
        $parts = explode(',', $usxReference);
        $canonicalParts = [];
        
        foreach ($parts as $part) {
            if (str_contains($part, '-')) {
                // Range: MAT_5_20-MAT_5_26
                [$start, $end] = explode('-', $part, 2);
                $startParts = explode('_', $start);
                $endParts = explode('_', $end);
                
                // Convert to canonical: "Mat 5,20-26"
                $bookAbbrev = \SzentirasHu\Data\UsxCodes::getPreferredAbbreviation($startParts[0], $translationAbbrev) ?? $startParts[0];
                $canonicalParts[] = "{$bookAbbrev} {$startParts[1]},{$startParts[2]}-{$endParts[2]}";
            } else {
                // Single verse: MAT_5_20
                $verseParts = explode('_', $part);
                $bookAbbrev = \SzentirasHu\Data\UsxCodes::getPreferredAbbreviation($verseParts[0], $translationAbbrev) ?? $verseParts[0];
                $canonicalParts[] = "{$bookAbbrev} {$verseParts[1]},{$verseParts[2]}";
            }
        }
        
        // Combine parts: if same book/chapter, combine verses
        if (count($canonicalParts) > 1) {
            // Simple implementation: just join with semicolon for now
            return implode('; ', $canonicalParts);
        }
        
        return $canonicalParts[0] ?? '';
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
        // Check if input is USX format (contains underscores)
        if (str_contains($referenceString, '_')) {
            // USX format: MAT_5_20-MAT_5_26 or MAT_5_20,MAT_5_21
            $firstPart = explode(',', $referenceString)[0];
            $firstVerse = str_contains($firstPart, '-') ? explode('-', $firstPart)[0] : $firstPart;
            return explode('_', $firstVerse)[0];
        }
        
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
        // If already USX format, return as-is
        if (str_contains($referenceString, '_')) {
            return $referenceString;
        }
        
        // Parse canonical reference
        $canonicalRef = \SzentirasHu\Service\Reference\CanonicalReference::fromString($referenceString);
        $usxParts = [];
        
        foreach ($canonicalRef->bookRefs as $bookRef) {
            $bookUsxCode = \SzentirasHu\Data\UsxCodes::getUsxFromBookAbbrevAndTranslation($bookRef->bookId, $translationAbbrev);
            if (!$bookUsxCode) {
                throw new \InvalidArgumentException("Could not find USX code for book: {$bookRef->bookId}");
            }
            
            foreach ($bookRef->chapterRanges as $chapterRange) {
                $chapterRef = $chapterRange->chapterRef;
                $chapterId = $chapterRef->chapterId;
                
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