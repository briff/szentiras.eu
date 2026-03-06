<?php

namespace SzentirasHu\Services\Tools;

use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Common service for all tools/games
 * Contains shared utility methods
 */
class ToolsService
{
    public function __construct(
        protected TextService $textService,
        protected TranslationService $translationService,
        protected BookService $bookService
    ) {
    }

    /**
     * Get random consecutive verses from a book
     */
    public function getRandomVersesFromBook($book, $translation, $minVerses = 2, $maxVerses = 4)
    {
        $maxChapter = $this->bookService->getChapterCount($book, $translation);
        if ($maxChapter === 0) {
            return null;
        }
        
        $randomChapter = rand(1, $maxChapter);
        $maxVerse = $this->bookService->getVerseCount($book, $randomChapter, $translation);
        
        if ($maxVerse === 0) {
            return null;
        }
        
        // Get random consecutive verses
        $verseCount = rand($minVerses, min($maxVerses, $maxVerse));
        $startVerse = rand(1, max(1, $maxVerse - $verseCount + 1));
        $endVerse = $startVerse + $verseCount - 1;
        
        try {
            $refString = "{$book->abbrev} {$randomChapter},{$startVerse}";
            if ($endVerse > $startVerse) {
                $refString .= "-{$endVerse}";
            }
            
            $canonicalRef = CanonicalReference::fromString($refString);
            $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
            
            $fullText = '';
            foreach ($verseContainers as $verseContainer) {
                foreach ($verseContainer->getParsedVerses() as $verse) {
                    // Get text without headings and strip HTML tags
                    $text = strip_tags($verse->getText('none'));
                    $fullText .= $text . ' ';
                }
            }
            
            return [
                'text' => trim($fullText),
                'reference' => $refString
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract text from verse containers
     */
    public function extractVerseText($verseContainers)
    {
        $fullText = '';
        foreach ($verseContainers as $verseContainer) {
            foreach ($verseContainer->getParsedVerses() as $verse) {
                $text = strip_tags($verse->getText('none'));
                $fullText .= $text . ' ';
            }
        }
        return trim($fullText);
    }

    /**
     * Extract words from text (remove punctuation, split by whitespace)
     */
    public function extractWords($text)
    {
        // Remove punctuation and numbers
        $cleanText = preg_replace('/[^\p{L}\s]/u', '', $text);
        // Split by whitespace
        $words = preg_split('/\s+/u', $cleanText);
        // Filter and clean
        $words = array_map('trim', $words);
        $words = array_filter($words, fn($w) => !empty($w));
        
        return array_values($words);
    }

    /**
     * Get all translations
     */
    public function getAllTranslations()
    {
        return $this->translationService->getAllTranslations();
    }

    /**
     * Get default translation
     */
    public function getDefaultTranslation()
    {
        return $this->translationService->getDefaultTranslation();
    }

    /**
     * Get translation by abbreviation
     */
    public function getTranslationByAbbreviation($abbrev)
    {
        return $this->translationService->getByAbbreviation($abbrev);
    }

    /**
     * Get books for a translation
     */
    public function getBooksForTranslation($translation)
    {
        return $this->bookService->getBooksForTranslation($translation);
    }

    /**
     * Get translated verses for a canonical reference
     */
    public function getTranslatedVerses($canonicalRef, $translation)
    {
        return $this->textService->getTranslatedVerses($canonicalRef, $translation);
    }
}
