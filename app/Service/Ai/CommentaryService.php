<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Support\Collection;
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
     *
     * @param string $usxCode
     * @param int $chapter
     * @param int $verse
     * @param Translation $translation
     * @return Collection<int, Commentary>
     */
    public function findForVerse(string $usxCode, int $chapter, int $verse, Translation $translation): Collection
    {
        return Commentary::query()
            ->where('translation_id', $translation->id)
            ->where('usx_code', $usxCode)
            ->whereHas('ranges', function ($query) use ($chapter, $verse) {
                $query->where(function ($q) use ($chapter, $verse) {
                    // Single verse
                    $q->where('start_chapter', $chapter)
                        ->where('start_verse', $verse)
                        ->where('end_chapter', $chapter)
                        ->where('end_verse', $verse);
                })->orWhere(function ($q) use ($chapter, $verse) {
                    // Within same chapter range
                    $q->where('start_chapter', $chapter)
                        ->where('end_chapter', $chapter)
                        ->where('start_verse', '<=', $verse)
                        ->where('end_verse', '>=', $verse);
                })->orWhere(function ($q) use ($chapter) {
                    // Cross-chapter range
                    $q->where('start_chapter', '<', $chapter)
                        ->where('end_chapter', '>', $chapter);
                })->orWhere(function ($q) use ($chapter, $verse) {
                    // Start chapter matches
                    $q->where('start_chapter', $chapter)
                        ->where('end_chapter', '>', $chapter)
                        ->where('start_verse', '<=', $verse);
                })->orWhere(function ($q) use ($chapter, $verse) {
                    // End chapter matches
                    $q->where('start_chapter', '<', $chapter)
                        ->where('end_chapter', $chapter)
                        ->where('end_verse', '>=', $verse);
                });
            })
            ->with('ranges')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find commentaries that cover any verse in a given reference.
     *
     * @param CanonicalReference $reference
     * @param Translation $translation
     * @return Collection<int, Commentary>
     */
    public function findForReference(CanonicalReference $reference, Translation $translation): Collection
    {
        // For simplicity, we'll find commentaries for each book in the reference.
        // Since commentaries are per book, we can query per book.
        $commentaries = collect();

        foreach ($reference->bookRefs as $bookRef) {
            $usxCode = $bookRef->bookId;
            foreach ($bookRef->chapterRanges as $chapterRange) {
                // We'll query for any commentary that overlaps with the chapter range.
                // This is a simplified approach; for production you might want to check verse-level overlap.
                $commentaries = $commentaries->merge(
                    Commentary::query()
                        ->where('translation_id', $translation->id)
                        ->where('usx_code', $usxCode)
                        ->whereHas('ranges', function ($query) use ($chapterRange) {
                            $query->where(function ($q) use ($chapterRange) {
                                // Overlap condition: start <= range.end AND end >= range.start
                                $q->where('start_chapter', '<=', $chapterRange->untilChapterRef->chapterId ?? $chapterRange->chapterRef->chapterId)
                                    ->where('end_chapter', '>=', $chapterRange->chapterRef->chapterId);
                            });
                        })
                        ->with('ranges')
                        ->get()
                );
            }
        }

        return $commentaries->unique('id');
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

        // Extract content from OpenAI response
        if (is_object($response) && property_exists($response, 'choices')) {
            return $response->choices[0]->message->content ?? '';
        }

        // Fallback: return raw response if structure differs
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