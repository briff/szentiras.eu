<?php

namespace SzentirasHu\Services\Tools;

use SzentirasHu\Service\Reference\CanonicalReference;

/**
 * Service for Word from Next Verse game
 */
class WordFromNextVerseService
{
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }

    /**
     * Generate a question for the game
     */
    public function generateQuestion($books, $translation): ?array
    {
        $maxAttempts = 50;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $attempts++;
            
            // Select a random book
            $randomBook = $books->random();
            $bookService = app(\SzentirasHu\Service\Text\BookService::class);
            $textService = app(\SzentirasHu\Service\Text\TextService::class);
            
            $maxChapter = $bookService->getChapterCount($randomBook, $translation);
            
            if ($maxChapter === 0) {
                continue;
            }
            
            $randomChapter = rand(1, $maxChapter);
            $maxVerse = $bookService->getVerseCount($randomBook, $randomChapter, $translation);
            
            if ($maxVerse < 2) {
                continue;
            }
            
            // Select a random verse (not the last one)
            $randomVerse = rand(1, $maxVerse - 1);
            
            try {
                // Get current verse
                $currentRef = "{$randomBook->abbrev} {$randomChapter},{$randomVerse}";
                $currentCanonicalRef = CanonicalReference::fromString($currentRef);
                $currentVerseContainers = $textService->getTranslatedVerses($currentCanonicalRef, $translation);
                
                // Get next verse
                $nextVerse = $randomVerse + 1;
                $nextRef = "{$randomBook->abbrev} {$randomChapter},{$nextVerse}";
                $nextCanonicalRef = CanonicalReference::fromString($nextRef);
                $nextVerseContainers = $textService->getTranslatedVerses($nextCanonicalRef, $translation);
                
                $currentText = $this->toolsService->extractVerseText($currentVerseContainers);
                $nextText = $this->toolsService->extractVerseText($nextVerseContainers);
                
                if (empty($currentText) || empty($nextText)) {
                    continue;
                }
                
                // Extract words from next verse
                $nextWords = $this->toolsService->extractWords($nextText);
                $nextWords = array_filter($nextWords, fn($w) => mb_strlen($w) >= 4);
                $nextWords = array_unique($nextWords);
                $nextWords = array_values($nextWords);
                
                if (count($nextWords) < 1) {
                    continue;
                }
                
                // Select correct word from next verse
                $correctWord = $nextWords[array_rand($nextWords)];
                
                // Get words from surrounding verses for wrong options
                $wrongWords = $this->getWrongWordOptions($randomBook, $randomChapter, $randomVerse, 
                                                         $translation, $currentText, $nextText, $correctWord);
                
                if (count($wrongWords) < 3) {
                    continue;
                }
                
                // Create options array and shuffle
                $options = array_merge([$correctWord], array_slice($wrongWords, 0, 3));
                shuffle($options);
                
                return [
                    'currentVerse' => $currentText,
                    'currentReference' => $currentRef,
                    'nextVerse' => $nextText,
                    'nextReference' => $nextRef,
                    'correctWord' => $correctWord,
                    'options' => $options
                ];
                
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }

    /**
     * Get wrong word options from surrounding verses
     */
    private function getWrongWordOptions($book, $chapter, $currentVerseNum, $translation, $currentText, $nextText, $correctWord)
    {
        $wrongWords = [];
        $bookService = app(\SzentirasHu\Service\Text\BookService::class);
        $textService = app(\SzentirasHu\Service\Text\TextService::class);
        
        $maxVerse = $bookService->getVerseCount($book, $chapter, $translation);
        
        // Get words from current verse
        $currentWords = $this->toolsService->extractWords($currentText);
        $currentWords = array_filter($currentWords, fn($w) => mb_strlen($w) >= 4);
        
        // Get words from next verse (excluding correct word)
        $nextWords = $this->toolsService->extractWords($nextText);
        $nextWords = array_filter($nextWords, fn($w) => mb_strlen($w) >= 4 && mb_strtolower($w) !== mb_strtolower($correctWord));
        
        // Combine and filter
        $candidateWords = array_merge($currentWords, $nextWords);
        $candidateWords = array_unique($candidateWords);
        $candidateWords = array_values($candidateWords);
        
        // Try to get more words from surrounding verses
        $versesToCheck = [];
        if ($currentVerseNum > 1) {
            $versesToCheck[] = $currentVerseNum - 1;
        }
        if ($currentVerseNum + 2 <= $maxVerse) {
            $versesToCheck[] = $currentVerseNum + 2;
        }
        
        foreach ($versesToCheck as $verseNum) {
            try {
                $ref = "{$book->abbrev} {$chapter},{$verseNum}";
                $canonicalRef = CanonicalReference::fromString($ref);
                $verseContainers = $textService->getTranslatedVerses($canonicalRef, $translation);
                $text = $this->toolsService->extractVerseText($verseContainers);
                
                $words = $this->toolsService->extractWords($text);
                $words = array_filter($words, fn($w) => mb_strlen($w) >= 4);
                
                $candidateWords = array_merge($candidateWords, $words);
            } catch (\Exception $e) {
                // Skip this verse
            }
        }
        
        // Filter out correct word and duplicates
        $candidateWords = array_filter($candidateWords, fn($w) => mb_strtolower($w) !== mb_strtolower($correctWord));
        $candidateWords = array_unique($candidateWords);
        $candidateWords = array_values($candidateWords);
        
        // Shuffle and return
        shuffle($candidateWords);
        
        return $candidateWords;
    }
}
