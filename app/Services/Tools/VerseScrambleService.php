<?php

namespace SzentirasHu\Services\Tools;

/**
 * Service for Verse Scramble game
 */
class VerseScrambleService
{
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }

    /**
     * Start a new game with scrambled verse
     */
    public function startNewGame($books, $translation): ?array
    {
        $maxAttempts = 10;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $attempts++;
            $randomBook = $books->random();
            $versesData = $this->toolsService->getRandomVersesFromBook($randomBook, $translation, 1, 1);
            
            if (!$versesData || empty($versesData['text'])) {
                continue;
            }
            
            // Split verse into words
            $verseText = $versesData['text'];
            
            // Clean text and split into words
            $words = array_filter(
                preg_split('/\s+/u', $verseText),
                function($word) {
                    return !empty(trim($word));
                }
            );
            
            $words = array_values($words);
            
            // Only use verses with at least 8 words
            if (count($words) >= 8) {
                // Create scrambled version
                $scrambledWords = $words;
                do {
                    shuffle($scrambledWords);
                } while ($scrambledWords === $words && count($words) > 1);
                
                return [
                    'verse' => $verseText,
                    'reference' => $versesData['reference'],
                    'words' => $words,
                    'scrambledWords' => $scrambledWords,
                    'attempts' => 0
                ];
            }
        }
        
        return null;
    }

    /**
     * Check if user's answer is correct
     */
    public function checkAnswer(array $userOrder, array $scrambledWords, array $correctWords): bool
    {
        // Reconstruct user's verse
        $userWords = [];
        foreach ($userOrder as $index) {
            if (isset($scrambledWords[$index])) {
                $userWords[] = $scrambledWords[$index];
            }
        }
        
        // Compare with original
        return $userWords === $correctWords;
    }
}
