<?php
namespace SzentirasHu\Http\Controllers\Display;

use SzentirasHu\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;

class GreekTextController extends Controller
{
    public function __construct(
        protected BookService $bookService,
        protected TranslationService $translationService,
        protected TranslationRepository $translationRepository,
        protected ReferenceService $referenceService
    )
    {
    }

    public function show(?string $reference = null)
    {
        $templateTranslation = 7;
        $books = $this->bookService->getBooksForTranslation($this->translationService->getById($templateTranslation));
        $translation = new Translation();
        $translation->abbrev = 'GNT';
        $translation->id = $templateTranslation; // Set ID for consistency
        
        $book = null;
        $greekVerses = collect();
        $canonicalRef = null;
        
        if ($reference) {
            try {
                $canonicalRef = CanonicalReference::fromString($reference, $templateTranslation);
                
                // Get the first book reference (GNT references should only have one book)
                if (count($canonicalRef->bookRefs) > 0) {
                    $bookRef = $canonicalRef->bookRefs[0];
                    $bookAbbrev = $bookRef->bookId;
                    
                    $book = collect($books)->firstWhere('abbrev', $bookAbbrev);
                    
                    if ($book) {
                        $query = GreekVerse::where('usx_code', $book->usx_code);
                        
                        // If there are chapter ranges, apply filters
                        if (count($bookRef->chapterRanges) > 0) {
                            $query->where(function ($q) use ($bookRef) {
                                foreach ($bookRef->chapterRanges as $chapterRange) {
                                    $chapterId = $chapterRange->chapterRef->chapterId;
                                    $untilChapterId = $chapterRange->untilChapterRef ? $chapterRange->untilChapterRef->chapterId : null;
                                    
                                    if ($untilChapterId) {
                                        // Chapter range
                                        $q->orWhereBetween('chapter', [$chapterId, $untilChapterId]);
                                    } else {
                                        // Single chapter
                                        $q->orWhere('chapter', $chapterId);
                                    }
                                    
                                    // If there are verse ranges within the chapter, we need to filter verses too
                                    // This is more complex and would require subqueries
                                    // For now, we'll get all verses in the chapter and filter in PHP
                                }
                            });
                        }
                        
                        $greekVerses = $query->orderBy('chapter')->orderBy('verse')->get();
                        
                        // If there are verse ranges, filter the collection
                        if (count($bookRef->chapterRanges) > 0) {
                            $greekVerses = $this->filterVersesByReference($greekVerses, $bookRef);
                        }
                    }
                }
            } catch (ParsingException $e) {
                // If parsing fails, fall back to treating it as a book abbreviation
                $book = collect($books)->firstWhere('abbrev', $reference);
                if ($book) {
                    $greekVerses = GreekVerse::where('usx_code', $book->usx_code)
                        ->orderBy('chapter')
                        ->orderBy('verse')
                        ->get();
                }
            }
        }
        
        // Get all translations for the translation switcher
        $allTranslations = $this->translationRepository->getAll();
        
        // Generate translation links
        $translationLinks = $allTranslations->map(function ($otherTranslation) use ($canonicalRef, $translation, $reference) {
            // For GNT to other translations, we need to check if the book exists in the other translation
            $enabled = true;
            $link = '';
            
            if ($otherTranslation->abbrev === 'GNT') {
                // Switching to GNT from GNT - stay on same page
                $link = $reference ? "/GNT/{$reference}" : "/GNT";
                $enabled = true;
            } else {
                // Switching to other translation from GNT
                if ($canonicalRef && count($canonicalRef->bookRefs) > 0) {
                    // Try to get the canonical URL for the other translation
                    try {
                        $link = $this->referenceService->getCanonicalUrl($canonicalRef, $otherTranslation->id);
                        $enabled = true;
                    } catch (\Exception $e) {
                        $enabled = false;
                        $link = '';
                    }
                } else if ($reference) {
                    // Simple book reference - try to convert
                    $link = "/{$otherTranslation->abbrev}/{$reference}";
                    $enabled = true;
                } else {
                    // No reference - just go to translation home
                    $link = "/{$otherTranslation->abbrev}";
                    $enabled = true;
                }
            }
            
            return [
                'id' => $otherTranslation->id,
                'link' => $link,
                'abbrev' => $otherTranslation->abbrev,
                'enabled' => $enabled
            ];
        })->sortBy(function ($translationLink) {
            // Put GNT at the end
            return $translationLink['abbrev'] === 'GNT' ? 1 : 0;
        })->values();
    
        return view('greekText.gnt', [
            'translation' => $translation,
            'books' => $books,
            'greekVerses' => $greekVerses,
            'book' => $book,
            'translationLinks' => $translationLinks
        ]);
    }
    
    /**
     * Filter Greek verses based on chapter/verse ranges from a BookRef
     */
    private function filterVersesByReference($verses, $bookRef)
    {
        return $verses->filter(function ($verse) use ($bookRef) {
            foreach ($bookRef->chapterRanges as $chapterRange) {
                $chapterId = $chapterRange->chapterRef->chapterId;
                $untilChapterId = $chapterRange->untilChapterRef ? $chapterRange->untilChapterRef->chapterId : null;
                
                // Check if verse is in chapter range
                if ($untilChapterId) {
                    if ($verse->chapter < $chapterId || $verse->chapter > $untilChapterId) {
                        continue;
                    }
                } else {
                    if ($verse->chapter != $chapterId) {
                        continue;
                    }
                }
                
                // If no verse ranges specified, include all verses in the chapter
                if (empty($chapterRange->chapterRef->verseRanges)) {
                    return true;
                }
                
                // Check verse ranges
                foreach ($chapterRange->chapterRef->verseRanges as $verseRange) {
                    $verseId = $verseRange->verseRef->verseId;
                    $untilVerseId = $verseRange->untilVerseRef ? $verseRange->untilVerseRef->verseId : null;
                    
                    if ($untilVerseId) {
                        if ($verse->verse >= $verseId && $verse->verse <= $untilVerseId) {
                            return true;
                        }
                    } else {
                        if ($verse->verse == $verseId) {
                            return true;
                        }
                    }
                }
            }
            
            return false;
        });
    }
}
