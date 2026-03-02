<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Support\Collection;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Models\CommentaryRange;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\BookService;

class CommentaryService
{
    public function __construct(
        private readonly TextService $textService,
        private readonly BookService $bookService,
    ) {}

    /**
     * Find commentaries that cover a specific verse.
     * Uses the same range-matching algorithm as CommentaryRange creation.
     *
     * @param string $usxCode
     * @param int $chapter
     * @param int $verse
     * @param Translation $translation
     * @return Collection<int, Commentary>
     */
    public function findForVerse(string $usxCode, int $chapter, int $verse, Translation $translation): Collection
    {
        // Create a single-verse range to search for
        $searchRange = [
            'start_chapter' => $chapter,
            'start_verse' => $verse,
            'end_chapter' => $chapter,
            'end_verse' => $verse,
        ];

        // Get all commentaries for this book and translation
        $commentaries = Commentary::query()
            ->where('translation_id', $translation->id)
            ->where('usx_code', $usxCode)
            ->with('ranges')
            ->orderBy('created_at', 'desc')
            ->get();

        // Filter commentaries that have overlapping ranges
        return $commentaries->filter(function (Commentary $commentary) use ($searchRange) {
            return $commentary->ranges->some(function (CommentaryRange $range) use ($searchRange) {
                return $this->rangesOverlap($range, $searchRange);
            });
        });
    }

    /**
     * Check if two ranges overlap.
     * Uses the same logic as range creation to ensure consistency.
     *
     * @param CommentaryRange $existingRange
     * @param array $searchRange Array with keys: start_chapter, start_verse, end_chapter, end_verse
     * @return bool
     */
    private function rangesOverlap(CommentaryRange $existingRange, array $searchRange): bool
    {
        $existing = [
            'start_chapter' => $existingRange->start_chapter,
            'start_verse' => $existingRange->start_verse,
            'end_chapter' => $existingRange->end_chapter,
            'end_verse' => $existingRange->end_verse,
        ];

        // Two ranges overlap if:
        // - They don't end before the other starts
        // - They don't start after the other ends
        // Using chapter:verse as a comparable unit

        // Convert to linear verse numbers for easier comparison
        $existingStart = $existing['start_chapter'] * 1000 + $existing['start_verse'];
        $existingEnd = $existing['end_chapter'] * 1000 + $existing['end_verse'];
        $searchStart = $searchRange['start_chapter'] * 1000 + $searchRange['start_verse'];
        $searchEnd = $searchRange['end_chapter'] * 1000 + $searchRange['end_verse'];

        // Ranges overlap if: existingStart <= searchEnd AND existingEnd >= searchStart
        return $existingStart <= $searchEnd && $existingEnd >= $searchStart;
    }

    /**
     * Check if two ranges are exactly equal.
     *
     * @param array $range1 Array with keys: start_chapter, start_verse, end_chapter, end_verse
     * @param array $range2 Array with keys: start_chapter, start_verse, end_chapter, end_verse
     * @return bool
     */
    private function rangesEqual(array $range1, array $range2): bool
    {
        return $range1['start_chapter'] === $range2['start_chapter']
            && $range1['start_verse'] === $range2['start_verse']
            && $range1['end_chapter'] === $range2['end_chapter']
            && $range1['end_verse'] === $range2['end_verse'];
    }

    /**
     * Check if a set of commentary ranges exactly matches a set of search ranges.
     * Order does not matter; ranges are compared after sorting.
     *
     * @param \Illuminate\Support\Collection<int, CommentaryRange> $commentaryRanges
     * @param array<array{start_chapter: int, start_verse: int, end_chapter: int, end_verse: int}> $searchRanges
     * @return bool
     */
    private function rangesSetEqual(\Illuminate\Support\Collection $commentaryRanges, array $searchRanges): bool
    {
        // Normalize commentary ranges to same format as search ranges
        $commentaryNormalized = $commentaryRanges->map(function (CommentaryRange $range) {
            return [
                'start_chapter' => $range->start_chapter,
                'start_verse' => $range->start_verse,
                'end_chapter' => $range->end_chapter,
                'end_verse' => $range->end_verse,
            ];
        })->sortBy(['start_chapter', 'start_verse', 'end_chapter', 'end_verse'])->values();

        $searchNormalized = collect($searchRanges)->sortBy(['start_chapter', 'start_verse', 'end_chapter', 'end_verse'])->values();

        if ($commentaryNormalized->count() !== $searchNormalized->count()) {
            return false;
        }

        foreach ($commentaryNormalized as $i => $range) {
            if (!$this->rangesEqual($range, $searchNormalized[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the earliest verse (chapter, verse) from a collection of commentary ranges.
     *
     * @param \Illuminate\Support\Collection<int, CommentaryRange> $commentaryRanges
     * @return array{chapter: int, verse: int}
     */
    private function getEarliestVerse(\Illuminate\Support\Collection $commentaryRanges): array
    {
        $earliestChapter = null;
        $earliestVerse = null;

        foreach ($commentaryRanges as $range) {
            $chapter = $range->start_chapter;
            $verse = $range->start_verse;

            if ($earliestChapter === null || $chapter < $earliestChapter || ($chapter === $earliestChapter && $verse < $earliestVerse)) {
                $earliestChapter = $chapter;
                $earliestVerse = $verse;
            }
        }

        return ['chapter' => $earliestChapter ?? 0, 'verse' => $earliestVerse ?? 0];
    }

    /**
     * Find commentaries that cover any verse in a given reference.
     * Uses the same range-matching algorithm as CommentaryRange creation.
     *
     * @param CanonicalReference $reference
     * @param Translation $translation
     * @return Collection<int, Commentary>
     */
    public function findForReference(CanonicalReference $reference, Translation $translation): Collection
    {
        $commentaryData = [];

        foreach ($reference->bookRefs as $bookRef) {
            // Determine if bookId is already a USX code or needs conversion
            $usxCode = $this->resolveUsxCode($bookRef->bookId, $translation);
            if (!$usxCode) {
                continue;
            }
            
            // Convert reference ranges to search ranges
            $searchRanges = $this->convertBookRefToSearchRanges($bookRef);
            $normalizedSearchRanges = $this->normalizeSearchRanges($searchRanges, $usxCode, $translation);

            // Get all commentaries for this book (no translation handling, as we assume there is only one translation per book in the Commentary model)
            $bookCommentaries = Commentary::query()
                ->where('usx_code', $usxCode)
                ->with('ranges')
                ->get();

            foreach ($bookCommentaries as $commentary) {
                // Check if commentary overlaps with any search range
                $overlaps = $commentary->ranges->some(function (CommentaryRange $range) use ($searchRanges) {
                    return collect($searchRanges)->some(function (array $searchRange) use ($range) {
                        return $this->rangesOverlap($range, $searchRange);
                    });
                });

                if (!$overlaps) {
                    continue;
                }

                $isExact = $this->rangesSetEqual($commentary->ranges, $normalizedSearchRanges);
                $earliest = $this->getEarliestVerse($commentary->ranges);

                // Store with commentary ID as key to deduplicate later
                $commentaryData[$commentary->id] = [
                    'commentary' => $commentary,
                    'exact' => $isExact,
                    'earliest_chapter' => $earliest['chapter'],
                    'earliest_verse' => $earliest['verse'],
                ];
            }
        }

        // Sort: exact matches first, then by earliest chapter, verse
        usort($commentaryData, function ($a, $b) {
            if ($a['exact'] !== $b['exact']) {
                return $a['exact'] ? -1 : 1; // true (exact) comes first
            }
            if ($a['earliest_chapter'] !== $b['earliest_chapter']) {
                return $a['earliest_chapter'] - $b['earliest_chapter'];
            }
            return $a['earliest_verse'] - $b['earliest_verse'];
        });

        // Attach is_exact property to each commentary for easy access
        $sortedCommentaries = collect($commentaryData)->map(function ($data) {
            $commentary = $data['commentary'];
            $commentary->is_exact = $data['exact'];
            return $commentary;
        });

        return $sortedCommentaries;
    }

    /**
     * Resolve a book identifier to a USX code.
     * Handles both book abbreviations and USX codes.
     *
     * @param string $bookId Book abbreviation or USX code
     * @param Translation $translation
     * @return string|null
     */
    private function resolveUsxCode(string $bookId, Translation $translation): ?string
    {
        // Check if it's already a valid USX code
        if (in_array($bookId, UsxCodes::allUsx())) {
            return $bookId;
        }

        // Try to convert from book abbreviation to USX code
        return UsxCodes::getUsxFromBookAbbrevAndTranslation($bookId, $translation->abbrev);
    }

    /**
     * Convert a BookRef with chapter ranges to search ranges.
     *
     * @param mixed $bookRef
     * @return array<array{start_chapter: int, start_verse: int, end_chapter: int, end_verse: int}>
     */
    private function convertBookRefToSearchRanges($bookRef): array
    {
        $ranges = [];

        foreach ($bookRef->chapterRanges as $chapterRange) {
            $startChapter = $chapterRange->chapterRef->chapterId;
            $endChapter = $chapterRange->untilChapterRef->chapterId ?? $startChapter;

            // If no verse ranges specified, use full chapter range
            if (empty($chapterRange->chapterRef->verseRanges)) {
                $ranges[] = [
                    'start_chapter' => $startChapter,
                    'start_verse' => 1,
                    'end_chapter' => $endChapter,
                    'end_verse' => 999, // Arbitrary high number for end of chapter
                ];
            } else {
                // Process each verse range
                foreach ($chapterRange->chapterRef->verseRanges as $verseRange) {
                    $startVerse = $verseRange->verseRef ? $verseRange->verseRef->verseId : 1;
                    $endVerse = $verseRange->untilVerseRef ? $verseRange->untilVerseRef->verseId : $startVerse;

                    $ranges[] = [
                        'start_chapter' => $startChapter,
                        'start_verse' => $startVerse,
                        'end_chapter' => $endChapter,
                        'end_verse' => $endVerse,
                    ];
                }
            }
        }

        return $ranges;
    }

    /**
     * Normalize search ranges by replacing placeholder end_verse 999 with actual verse count.
     *
     * @param array<array{start_chapter: int, start_verse: int, end_chapter: int, end_verse: int}> $searchRanges
     * @param string $usxCode
     * @param Translation $translation
     * @return array<array{start_chapter: int, start_verse: int, end_chapter: int, end_verse: int}>
     */
    private function normalizeSearchRanges(array $searchRanges, string $usxCode, Translation $translation): array
    {
        $normalized = [];
        foreach ($searchRanges as $range) {
            // Detect full chapter placeholder (start_verse=1, end_verse=999)
            if ($range['start_verse'] === 1 && $range['end_verse'] === 999) {
                $book = $this->bookService->getBookByUsxCodeTranslation($usxCode, $translation->abbrev);
                $verseCount = $this->bookService->getVerseCount($book, $range['start_chapter'], $translation);
                $range['end_verse'] = $verseCount;
            }
            $normalized[] = $range;
        }
        return $normalized;
    }

    /**
     * Store a new commentary with its ranges.
     *
     * @param Translation $translation
     * @param string $usxCode
     * @param string $commentaryText
     * @param array $ranges Array of arrays with keys: start_chapter, start_verse, end_chapter, end_verse
     * @param array $metadata Optional metadata (AI model, prompt version, etc.)
     * @param string|null $sourceText The source text (verse) that the commentary was generated for
     * @param int|null $tokenUsage Token usage count from AI generation
     * @return Commentary
     */
    public function store(
        Translation $translation,
        string $usxCode,
        string $commentaryText,
        array $ranges,
        array $metadata = [],
        ?string $sourceText = null,
        ?int $tokenUsage = null
    ): Commentary {
        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => $usxCode,
            'commentary_text' => $commentaryText,
            'metadata' => $metadata,
            'status' => Commentary::STATUS_COMPLETED,
            'started_at' => now(),
            'completed_at' => now(),
            'source_text' => $sourceText,
            'token_usage' => $tokenUsage,
        ]);

        foreach ($ranges as $range) {
            $commentary->ranges()->create([
                'start_chapter' => $range['start_chapter'],
                'start_verse' => $range['start_verse'],
                'end_chapter' => $range['end_chapter'],
                'end_verse' => $range['end_verse'],
            ]);
        }

        return $commentary->load('ranges');
    }

    /**
     * Create a pending commentary record (without generated text).
     *
     * @param Translation $translation
     * @param string $usxCode
     * @param array $ranges
     * @param array $metadata
     * @return Commentary
     */
    public function createPendingCommentary(
        Translation $translation,
        string $usxCode,
        array $ranges,
        array $metadata = []
    ): Commentary {
        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => $usxCode,
            'commentary_text' => null,
            'metadata' => $metadata,
            'status' => Commentary::STATUS_PENDING,
            // started_at and completed_at remain null
        ]);

        foreach ($ranges as $range) {
            $commentary->ranges()->create([
                'start_chapter' => $range['start_chapter'],
                'start_verse' => $range['start_verse'],
                'end_chapter' => $range['end_chapter'],
                'end_verse' => $range['end_verse'],
            ]);
        }

        return $commentary->load('ranges');
    }

    /**
     * Generate commentary text for a given reference using AI.
     *
     * @param CanonicalReference $reference
     * @param Translation $translation
     * @param AiPromptService $aiPromptService
     * @param int $maxLength Maximum allowed commentary text length (characters)
     * @param bool $force If true, bypass length validation
     * @param bool $useBatch Whether to use OpenAI batch API
     * @param int|null $commentaryId Optional commentary ID to associate with batch item
     * @return array{text: string, source_text: string, token_usage: int}
     * @throws \RuntimeException If commentary text exceeds maxLength and force is false
     * @throws \RuntimeException If daily token usage limit is exceeded and force is false
     */
    public function generateCommentaryText(
        CanonicalReference $reference,
        Translation $translation,
        AiPromptService $aiPromptService,
        int $maxLength,
        bool $force = false,
        bool $useBatch = false,
        ?int $commentaryId = null
    ): array {
        $verseText = $this->textService->getPureText($reference, $translation);

        // if no text, throw an exception to avoid generating empty commentary
        if (empty($verseText)) {
            throw new \RuntimeException('No verse text found for the given reference and translation. Cannot generate commentary.');
        }   

                // Length validation
        if (!$force && strlen($verseText) > $maxLength) {
            throw new \RuntimeException(sprintf(
                'Verse text length (%d characters) exceeds maximum allowed length (%d characters). Use --force to bypass.',
                strlen($verseText),
                $maxLength
            ));
        }

        // Daily token usage validation
        if (!$force) {
            $maxTokenPerDay = config('ai.configurations.commentary.max_token_per_day', 0);
            $usedTokens = $this->sumTokenUsageForDay();
            if ($usedTokens >= $maxTokenPerDay) {
                throw new \RuntimeException(sprintf(
                    'Daily token usage limit (%d tokens) already exceeded (%d tokens used). Use --force to bypass.',
                    $maxTokenPerDay,
                    $usedTokens
                ));
            }
        }

        $placeholders = ['verse_text' => $verseText,
            'reference' => $reference->toString(),
            'translation' => $translation->abbrev,
            // we estimate the token count by assuming 2.5 characters per token.
            'max_tokens' => (int) ceil(strlen($verseText) / 2.5),
        ];

        $response = $aiPromptService->generate('commentary', $useBatch, $placeholders, $commentaryId);

        // If using batch mode, response will be null
        if ($useBatch) {
            // Batch job submitted, return empty result
            return [
                'text' => '',
                'source_text' => $verseText,
                'token_usage' => 0,
            ];
        }

        // Extract content from OpenAI Responses API structure
        $text = '';
        $tokenUsage = 0;
        if (is_object($response) && property_exists($response, 'output')) {
            $text = $response->output[0]->content[0]->text ?? '';
            if (property_exists($response, 'usage') && is_object($response->usage) && property_exists($response->usage, 'totalTokens')) {
                $tokenUsage = $response->usage->totalTokens;
            }
        } else {
            $text = (string) $response;
        }

        return [
            'text' => $text,
            'source_text' => $verseText,
            'token_usage' => $tokenUsage,
        ];
    }

    /**
     * Parse a reference string into ranges suitable for storage.
     *
     * Example: "MAT_1_2-MAT_1_6,MAT_1_12,MAT_1_23-MAT_2_5"
     * Returns array of ranges for book MAT.
     *
     * @param string $referenceString
     * @param string $usxCode
     * @return array<array{start_chapter: int, start_verse: int, end_chapter: int, end_verse: int}>
     */
    public function parseRangesFromReference(string $referenceString, string $usxCode): array
    {
        $ranges = [];
        $parts = explode(',', $referenceString);

        foreach ($parts as $part) {
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part, 2);
                $startParts = explode('_', $start);
                $endParts = explode('_', $end);

                // Validate format
                if (count($startParts) < 3) {
                    throw new \InvalidArgumentException("Invalid USX format: '{$start}'. Expected format: BOOK_CHAPTER_VERSE (e.g., MAT_5_20)");
                }
                if (count($endParts) < 3) {
                    throw new \InvalidArgumentException("Invalid USX format: '{$end}'. Expected format: BOOK_CHAPTER_VERSE (e.g., MAT_5_20)");
                }

                // Validate USX code matches
                if ($startParts[0] !== $usxCode || $endParts[0] !== $usxCode) {
                    throw new \InvalidArgumentException("Range must be within the same book: {$usxCode}");
                }

                $ranges[] = [
                    'start_chapter' => (int) $startParts[1],
                    'start_verse' => (int) $startParts[2],
                    'end_chapter' => (int) $endParts[1],
                    'end_verse' => (int) $endParts[2],
                ];
            } else {
                $verseParts = explode('_', $part);
                if (count($verseParts) < 3) {
                    throw new \InvalidArgumentException("Invalid USX format: '{$part}'. Expected format: BOOK_CHAPTER_VERSE (e.g., MAT_5_20)");
                }
                if ($verseParts[0] !== $usxCode) {
                    throw new \InvalidArgumentException("Verse must belong to book: {$usxCode}");
                }
                $ranges[] = [
                    'start_chapter' => (int) $verseParts[1],
                    'start_verse' => (int) $verseParts[2],
                    'end_chapter' => (int) $verseParts[1],
                    'end_verse' => (int) $verseParts[2],
                ];
            }
        }

        return $ranges;
    }

    /**
     * Sum the token_usage of Commentaries created on the given day.
     *
     * @param \Carbon\Carbon|\DateTime|string $date Date to filter by (defaults to today)
     * @return int Total token usage for the day
     */
    public function sumTokenUsageForDay($date = null): int
    {
        if ($date === null) {
            $date = now();
        } elseif (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        } elseif (!$date instanceof \Carbon\Carbon) {
            $date = \Carbon\Carbon::instance($date);
        }

        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        return (int) Commentary::query()
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->sum('token_usage');
    }
}