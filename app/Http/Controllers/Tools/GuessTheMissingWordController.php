<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\GuessTheMissingWordService;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for Guess the Missing Word game (Wordle-like)
 */
class GuessTheMissingWordController extends Controller
{
    public function __construct(
        protected GuessTheMissingWordService $guessTheMissingWordService,
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
        $gameState = $request->session()->get('guess_word_state', null);
        $guesses = [];
        $won = false;
        $maxGuesses = 6;
        
        // Start new game
        if ($request->input('action') === 'new_game' || !$gameState || $gameState['translation'] !== $selectedTranslation) {
            $gameState = $this->guessTheMissingWordService->startNewGame($books, $translation);
            
            if ($gameState) {
                $gameState['translation'] = $selectedTranslation;
                $request->session()->put('guess_word_state', $gameState);
            }
        }
        
        // Process guess
        if ($request->input('action') === 'guess' && $request->has('guess_word') && $gameState) {
            $guessInput = $request->input('guess_word');
            $guessNormalized = $this->guessTheMissingWordService->normalizeWord($guessInput);
            
            if (mb_strlen($guessNormalized) === mb_strlen($gameState['wordNormalized'])) {
                $guess = $this->guessTheMissingWordService->processGuess($guessInput, $gameState['wordNormalized']);
                
                $gameState['guesses'][] = $guess;
                $request->session()->put('guess_word_state', $gameState);
                
                // Check if won
                if ($guessNormalized === $gameState['wordNormalized']) {
                    $won = true;
                }
            }
        }
        
        // Process give up
        $gaveUp = false;
        if ($request->input('action') === 'give_up' && $gameState) {
            $gaveUp = true;
        }
        
        $guesses = $gameState['guesses'] ?? [];
        $gameOver = $won || count($guesses) >= $maxGuesses || $gaveUp;
        
        return \View::make("tools/guess-word", [
            'pageTitle' => 'Találd ki a hiányzó szót - Szentírás.eu',
            'metaTitle' => 'Találd ki a hiányzó szót - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'versesWithGap' => $gameState['versesWithGap'] ?? '',
            'reference' => $gameState['reference'] ?? null,
            'wordLength' => isset($gameState['wordNormalized']) ? mb_strlen($gameState['wordNormalized']) : 0,
            'guesses' => $guesses,
            'won' => $won,
            'gaveUp' => $gaveUp,
            'gameOver' => $gameOver,
            'maxGuesses' => $maxGuesses,
            'correctWord' => $gameOver && !$won ? $gameState['word'] : null
        ]);
    }
}
