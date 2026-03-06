<?php

namespace SzentirasHu\Services\Tools;

/**
 * Service for Guess the Missing Word game (Wordle-like)
 */
class GuessTheMissingWordService
{
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }

    /**
     * Start a new game
     */
    public function startNewGame($books, $translation): ?array
    {
        $randomBook = $books->random();
        $versesData = $this->toolsService->getRandomVersesFromBook($randomBook, $translation, 3, 4);
        
        if (!$versesData || empty($versesData['text'])) {
            return null;
        }
        
        // Find a word with at least 4 letters
        $wordData = $this->selectWordFromText($versesData['text']);
        
        if (!$wordData) {
            return null;
        }
        
        return [
            'verses' => $versesData['text'],
            'reference' => $versesData['reference'],
            'versesWithGap' => $wordData['textWithGap'],
            'word' => $wordData['word'],
            'wordNormalized' => $wordData['wordNormalized'],
            'guesses' => []
        ];
    }

    /**
     * Process a guess and return evaluation
     */
    public function processGuess(string $guessInput, string $targetWordNormalized): array
    {
        $guessNormalized = $this->normalizeWord($guessInput);
        $evaluation = $this->evaluateGuess($guessNormalized, $targetWordNormalized);
        
        // Split normalized word into characters array
        $chars = preg_split('//u', $guessNormalized, -1, PREG_SPLIT_NO_EMPTY);
        
        return [
            'word' => $guessInput,
            'wordNormalized' => $guessNormalized,
            'chars' => $chars,
            'evaluation' => $evaluation
        ];
    }

    /**
     * Select a word from text (at least 4 letters, no punctuation)
     */
    private function selectWordFromText(string $text): ?array
    {
        // Remove punctuation and split into words
        $cleanText = preg_replace('/[^\p{L}\s]/u', '', $text);
        $words = array_filter(explode(' ', $cleanText), function($word) {
            return mb_strlen(trim($word)) >= 4;
        });
        
        if (empty($words)) {
            return null;
        }
        
        // Select a random word
        $wordsArray = array_values($words);
        $selectedWord = $wordsArray[array_rand($wordsArray)];
        $selectedWord = trim($selectedWord);
        
        // Create text with gap (use underlined placeholder)
        $wordLength = mb_strlen($selectedWord);
        $placeholder = '<span class="word-gap">' . str_repeat('_', $wordLength) . '</span>';
        $textWithGap = str_replace($selectedWord, $placeholder, $text);
        
        return [
            'word' => $selectedWord,
            'wordNormalized' => $this->normalizeWord($selectedWord),
            'textWithGap' => $textWithGap
        ];
    }

    /**
     * Normalize word: lowercase and remove punctuation
     */
    public function normalizeWord(string $word): string
    {
        // Remove punctuation
        $word = preg_replace('/[^\p{L}]/u', '', $word);
        // Convert to lowercase (UTF-8 safe)
        return mb_strtolower($word, 'UTF-8');
    }

    /**
     * Evaluate a guess against the target word (Wordle-like)
     * Returns array of letter evaluations: 'correct', 'present', 'absent'
     */
    private function evaluateGuess(string $guess, string $target): array
    {
        $evaluation = [];
        $guessChars = preg_split('//u', $guess, -1, PREG_SPLIT_NO_EMPTY);
        $targetChars = preg_split('//u', $target, -1, PREG_SPLIT_NO_EMPTY);
        $targetCharCount = array_count_values($targetChars);
        $usedTargetChars = array_fill(0, count($targetChars), false);
        
        // First pass: mark correct positions (green)
        foreach ($guessChars as $i => $char) {
            if ($char === $targetChars[$i]) {
                $evaluation[$i] = 'correct';
                $usedTargetChars[$i] = true;
                $targetCharCount[$char]--;
            } else {
                $evaluation[$i] = null; // Will be determined in second pass
            }
        }
        
        // Second pass: mark present but wrong position (yellow)
        foreach ($guessChars as $i => $char) {
            if ($evaluation[$i] === null) {
                if (isset($targetCharCount[$char]) && $targetCharCount[$char] > 0) {
                    $evaluation[$i] = 'present';
                    $targetCharCount[$char]--;
                } else {
                    $evaluation[$i] = 'absent';
                }
            }
        }
        
        return $evaluation;
    }
}
