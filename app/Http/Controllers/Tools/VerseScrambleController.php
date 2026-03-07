<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\VerseScrambleService;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for Verse Scramble game
 */
class VerseScrambleController extends Controller
{
    public function __construct(
        protected VerseScrambleService $verseScrambleService,
        protected ToolsService $toolsService
    ) {
    }

    public function index(Request $request)
    {
        $translations = $this->toolsService->getAllTranslations();
        $selectedTranslation = null;

        // Set default translation for initial page load
        if (!$request->isMethod('post') || !$request->has('translation_abbrev')) {
            $defaultTranslation = $this->toolsService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        } else {
            $selectedTranslation = $request->input('translation_abbrev');
        }

        $translation = $this->toolsService->getTranslationByAbbreviation($selectedTranslation);
        $books = $this->toolsService->getBooksForTranslation($translation);

        // Initialize or get game state from session
        $gameState = $request->session()->get('verse_scramble_state', null);
        $won = false;

        // Start new game only if explicitly requested
        if ($request->input('action') === 'new_game') {
            $gameState = $this->verseScrambleService->startNewGame($books, $translation);

            if ($gameState) {
                $gameState['translation'] = $selectedTranslation;
                $request->session()->put('verse_scramble_state', $gameState);
            } else {
                // If failed to create game, show error
                $gameState = [
                    'translation' => $selectedTranslation,
                    'scrambledWords' => [],
                    'reference' => null,
                    'attempts' => 0,
                    'error' => 'Nem sikerült megfelelő verset találni. Próbáld újra!'
                ];
                $request->session()->put('verse_scramble_state', $gameState);
            }
        }
        // Reset if translation changed
        elseif ($gameState && isset($gameState['translation']) && $gameState['translation'] !== $selectedTranslation) {
            $gameState = null;
            $request->session()->forget('verse_scramble_state');
        }

        // Check answer
        if ($request->input('action') === 'check' && $gameState && isset($gameState['scrambledWords']) && isset($gameState['words'])) {
            $userOrder = $request->input('word_order', []);
            
            $won = $this->verseScrambleService->checkAnswer(
                $userOrder,
                $gameState['scrambledWords'],
                $gameState['words']
            );

            if (isset($gameState['attempts'])) {
                $gameState['attempts']++;
            }
            $request->session()->put('verse_scramble_state', $gameState);
        }

        return \View::make("tools/verse-scramble", [
            'pageTitle' => 'Verskirakó játék - Szentírás.eu',
            'metaTitle' => 'Verskirakó játék - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'scrambledWords' => $gameState['scrambledWords'] ?? [],
            'reference' => $gameState['reference'] ?? null,
            'won' => $won,
            'attempts' => $gameState['attempts'] ?? 0,
            'correctVerse' => $won ? ($gameState['verse'] ?? null) : null,
            'error' => $gameState['error'] ?? null
        ]);
    }
}
