<?php

namespace SzentirasHu\Services\Tools;

/**
 * Service for Guess the Book game (bibDLE)
 */
class GuessTheBookService
{
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }

    /**
     * Start a new guess book game
     */
    public function startNewGame($books, $translation): ?array
    {
        $randomBook = $books->random();
        $versesData = $this->toolsService->getRandomVersesFromBook($randomBook, $translation);
        
        if (!$versesData) {
            return null;
        }
        
        return [
            'book' => [
                'id' => $randomBook->id,
                'name' => $randomBook->name,
                'abbrev' => $randomBook->abbrev,
                'testament' => $randomBook->old_testament ? 'old' : 'new',
                'section' => $this->getBookSection($randomBook->order, $randomBook->old_testament),
                'firstLetter' => mb_substr($randomBook->name, 0, 1, 'UTF-8')
            ],
            'verses' => $versesData['text'],
            'guesses' => []
        ];
    }

    /**
     * Process a guess
     */
    public function processGuess($guessedBook, $correctBookData): array
    {
        $guess = [
            'book' => [
                'id' => $guessedBook->id,
                'name' => $guessedBook->name,
                'abbrev' => $guessedBook->abbrev,
                'testament' => $guessedBook->old_testament ? 'old' : 'new',
                'section' => $this->getBookSection($guessedBook->order, $guessedBook->old_testament),
                'firstLetter' => mb_substr($guessedBook->name, 0, 1, 'UTF-8')
            ],
            'matches' => [
                'testament' => ($guessedBook->old_testament ? 'old' : 'new') === $correctBookData['testament'],
                'section' => $this->getBookSection($guessedBook->order, $guessedBook->old_testament) === $correctBookData['section'],
                'firstLetter' => mb_substr($guessedBook->name, 0, 1, 'UTF-8') === $correctBookData['firstLetter'],
                'book' => $guessedBook->id === $correctBookData['id']
            ]
        ];
        
        return $guess;
    }

    /**
     * Get the section of a book based on its order and testament
     */
    private function getBookSection($order, $oldTestament)
    {
        if ($oldTestament) {
            // Old Testament: orders 101-146 (normalized to 1-46)
            $normalizedOrder = $order >= 100 ? $order - 100 : $order;
            
            if ($normalizedOrder >= 1 && $normalizedOrder <= 5) {
                return 'Tóra';
            } elseif ($normalizedOrder >= 6 && $normalizedOrder <= 17) {
                return 'Történelmi könyvek';
            } elseif ($normalizedOrder >= 18 && $normalizedOrder <= 22) {
                return 'Bölcsességi könyvek';
            } elseif ($normalizedOrder >= 23 && $normalizedOrder <= 39) {
                return 'Próféták';
            } else {
                return 'Deuterokanonikus könyvek';
            }
        } else {
            // New Testament: orders 201-227 (normalized to 1-27)
            $normalizedOrder = $order >= 200 ? $order - 200 : $order;
            
            if ($normalizedOrder >= 1 && $normalizedOrder <= 4) {
                return 'Evangéliumok';
            } elseif ($normalizedOrder === 5) {
                return 'Apostolok Cselekedetei';
            } elseif ($normalizedOrder >= 6 && $normalizedOrder <= 19) {
                return 'Pál levelei';
            } elseif ($normalizedOrder >= 20 && $normalizedOrder <= 26) {
                return 'Katolikus levelek';
            } elseif ($normalizedOrder === 27) {
                return 'Jelenések könyve';
            }
        }
        
        return 'Egyéb';
    }
}
