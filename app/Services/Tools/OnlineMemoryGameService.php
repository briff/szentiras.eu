<?php

namespace SzentirasHu\Services\Tools;

/**
 * Service for Online Memory Game tool
 */
class OnlineMemoryGameService
{
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }

    /**
     * Generate memory game cards
     */
    public function generateCards(int $rows, int $cols, $translation, $books): array
    {
        $cards = [];
        $errors = [];
        
        // Validate rows and cols
        if ($rows < 2) {
            $errors[] = 'A sorok száma legalább 2 legyen.';
        }
        if ($cols < 2) {
            $errors[] = 'Az oszlopok száma legalább 2 legyen.';
        }
        
        $totalCards = $rows * $cols;
        if ($totalCards % 2 !== 0) {
            $errors[] = 'A kártyák száma (' . $totalCards . ') nem páros. Kérem válasszon olyan sort és oszlopot, amelyek szorzata páros!';
        }
        
        if (!empty($errors)) {
            return ['cards' => [], 'errors' => $errors];
        }
        
        $pairsNeeded = $totalCards / 2;
        
        // Generate random verses
        $attempts = 0;
        $maxAttempts = 50 + $pairsNeeded; // Prevent infinite loop
        
        while (count($cards) < $pairsNeeded && $attempts < $maxAttempts) {
            $attempts++;
            $randomBook = $books->random();
            $versesData = $this->toolsService->getRandomVersesFromBook($randomBook, $translation, 1, 2);
            
            if (!$versesData || empty($versesData['text'])) {
                continue;
            }
            
            $verseText = $versesData['text'];
            
            // Split verse into two halves
            $words = explode(' ', $verseText);
            $wordCount = count($words);
            
            if ($wordCount < 6) {
                continue; // Too short
            }
            
            $halfPoint = (int)($wordCount / 2);
            $firstHalf = implode(' ', array_slice($words, 0, $halfPoint));
            $secondHalf = implode(' ', array_slice($words, $halfPoint));
            
            // Normalize card text
            $firstHalf = $this->normalizeCardText($firstHalf);
            $secondHalf = $this->normalizeCardText($secondHalf);
            
            $cards[] = [
                'firstHalf' => $firstHalf,
                'secondHalf' => $secondHalf,
                'pairId' => count($cards)
            ];
        }
        
        if (count($cards) < $pairsNeeded) {
            $errors[] = 'Nem sikerült elegendő verset találni. Próbálja meg kevesebb kártyával.';
            $cards = [];
        }
        
        return ['cards' => $cards, 'errors' => $errors];
    }

    /**
     * Normalize card text: capitalize first letter and remove trailing punctuation
     */
    private function normalizeCardText(string $text): string
    {
        // Remove trailing punctuation marks
        $text = rtrim($text, '.,;:!?"\'…');
        
        // Capitalize first letter (UTF-8 safe)
        $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
        
        return $text;
    }
}
