<?php

namespace SzentirasHu\Services\Tools;

use SzentirasHu\Data\UsxCodes;

/**
 * Service for Guess the Book game (bibDLE)
 */
class GuessTheBookService
{
    private const TORAH_USX = ['GEN', 'EXO', 'LEV', 'NUM', 'DEU'];
    private const HISTORICAL_USX = ['JOS', 'JDG', 'RUT', '1SA', '2SA', '1KI', '2KI', '1CH', '2CH', 'EZR', 'NEH', 'EST'];
    private const WISDOM_USX = ['JOB', 'PSA', 'PRO', 'ECC', 'SNG'];
    private const PROPHETS_USX = ['ISA', 'JER', 'LAM', 'EZK', 'DAN', 'HOS', 'JOL', 'AMO', 'OBA', 'JON', 'MIC', 'NAM', 'HAB', 'ZEP', 'HAG', 'ZEC', 'MAL'];
    private const GOSPELS_USX = ['MAT', 'MRK', 'LUK', 'JHN'];
    private const ACTS_USX = ['ACT'];
    private const PAULINE_USX = ['ROM', '1CO', '2CO', 'GAL', 'EPH', 'PHP', 'COL', '1TH', '2TH', '1TI', '2TI', 'TIT', 'PHM', 'HEB'];
    private const CATHOLIC_LETTERS_USX = ['JAS', '1PE', '2PE', '1JN', '2JN', '3JN', 'JUD'];
    private const REVELATION_USX = ['REV'];

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
                'section' => $this->getBookSection($randomBook->usx_code, $randomBook->order, $randomBook->old_testament),
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
                'section' => $this->getBookSection($guessedBook->usx_code, $guessedBook->order, $guessedBook->old_testament),
                'firstLetter' => mb_substr($guessedBook->name, 0, 1, 'UTF-8')
            ],
            'matches' => [
                'testament' => ($guessedBook->old_testament ? 'old' : 'new') === $correctBookData['testament'],
                'section' => $this->getBookSection($guessedBook->usx_code, $guessedBook->order, $guessedBook->old_testament) === $correctBookData['section'],
                'firstLetter' => mb_substr($guessedBook->name, 0, 1, 'UTF-8') === $correctBookData['firstLetter'],
                'book' => $guessedBook->id === $correctBookData['id']
            ]
        ];
        
        return $guess;
    }

    /**
     * Get the section of a book based on its USX code.
     */
    private function getBookSection(?string $usxCode, $order = null, $oldTestament = null): string
    {
        $normalizedUsxCode = $usxCode !== null ? mb_strtoupper(trim($usxCode), 'UTF-8') : null;

        if ($normalizedUsxCode !== null) {
            if (in_array($normalizedUsxCode, self::TORAH_USX, true)) {
                return 'Tóra';
            }

            if (in_array($normalizedUsxCode, self::HISTORICAL_USX, true)) {
                return 'Történelmi könyvek';
            }

            if (in_array($normalizedUsxCode, self::WISDOM_USX, true)) {
                return 'Bölcsességi könyvek';
            }

            if (in_array($normalizedUsxCode, self::PROPHETS_USX, true)) {
                return 'Próféták';
            }

            if (in_array($normalizedUsxCode, UsxCodes::oldTestamentUsx(), true)) {
                return 'Deuterokanonikus könyvek';
            }

            if (in_array($normalizedUsxCode, self::GOSPELS_USX, true)) {
                return 'Evangéliumok';
            }

            if (in_array($normalizedUsxCode, self::ACTS_USX, true)) {
                return 'Apostolok Cselekedetei';
            }

            if (in_array($normalizedUsxCode, self::PAULINE_USX, true)) {
                return 'Pál levelei';
            }

            if (in_array($normalizedUsxCode, self::CATHOLIC_LETTERS_USX, true)) {
                return 'Katolikus levelek';
            }

            if (in_array($normalizedUsxCode, self::REVELATION_USX, true)) {
                return 'Jelenések könyve';
            }
        }

        if ($order !== null && $oldTestament !== null) {
            return $this->getBookSectionFromLegacyOrder((int) $order, (bool) $oldTestament);
        }
        
        return 'Egyéb';
    }

    private function getBookSectionFromLegacyOrder(int $order, bool $oldTestament): string
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
        }

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

        return 'Egyéb';
    }
}
