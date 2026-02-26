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

class CommentaryService
{
    public function __construct(
        private readonly TextService $textService,
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

            // Get all commentaries for this book and translation
            $bookCommentaries = Commentary::query()
                ->where('translation_id', $translation->id)
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

                $isExact = $this->rangesSetEqual($commentary->ranges, $searchRanges);
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
     * Store a new commentary with its ranges.
     *
     * @param Translation $translation
     * @param string $usxCode
     * @param string $commentaryText
     * @param array $ranges Array of arrays with keys: start_chapter, start_verse, end_chapter, end_verse
     * @param array $metadata Optional metadata (AI model, prompt version, etc.)
     * @return Commentary
     */
    public function store(
        Translation $translation,
        string $usxCode,
        string $commentaryText,
        array $ranges,
        array $metadata = []
    ): Commentary {
        $commentary = Commentary::create([
            'translation_id' => $translation->id,
            'usx_code' => $usxCode,
            'commentary_text' => $commentaryText,
            'metadata' => $metadata,
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
     * @param array $additionalPlaceholders
     * @return string
     */
    public function generateCommentaryText(
        CanonicalReference $reference,
        Translation $translation,
        AiPromptService $aiPromptService,
        array $additionalPlaceholders = []
    ): string {
        $verseText = $this->textService->getPureText($reference, $translation);

        $placeholders = array_merge([
            'verse_text' => $verseText,
            'reference' => $reference->toString(),
            'translation' => $translation->abbrev,
        ], $additionalPlaceholders);

        $config = $aiPromptService->resolveConfiguration('commentary');
        $prompt = $aiPromptService->replacePlaceholders($config, $placeholders);

        $response = $aiPromptService->generate('commentary', $placeholders);

        // Extract content from OpenAI Responses API structure
        if (is_object($response) && property_exists($response, 'output')) {
            return $response->output[0]->content[0]->text ?? '';
        }

        return (string) $response;
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
}