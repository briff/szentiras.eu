<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\GuessTheBookService;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for Guess the Book game (bibDLE)
 */
class GuessTheBookController extends Controller
{
    public function __construct(
        protected GuessTheBookService $guessTheBookService,
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
        $gameState = $request->session()->get('guess_book_state', null);
        $guesses = [];
        $won = false;
        
        // Start new game
        if ($request->input('action') === 'new_game' || !$gameState || $gameState['translation'] !== $selectedTranslation) {
            $gameState = $this->guessTheBookService->startNewGame($books, $translation);
            
            if ($gameState) {
                $gameState['translation'] = $selectedTranslation;
                $request->session()->put('guess_book_state', $gameState);
            }
        }
        
        // Process guess
        if ($request->input('action') === 'guess' && $request->has('book_id')) {
            $guessedBookId = (int)$request->input('book_id');
            $guessedBook = $books->firstWhere('id', $guessedBookId);
            
            if ($guessedBook) {
                $guess = $this->guessTheBookService->processGuess($guessedBook, $gameState['book']);
                
                $gameState['guesses'][] = $guess;
                $request->session()->put('guess_book_state', $gameState);
                
                if ($guess['matches']['book']) {
                    $won = true;
                }
            }
        }
        
        $guesses = $gameState['guesses'] ?? [];
        
        return \View::make("tools/guess-book", [
            'pageTitle' => 'Találd ki a könyvet - Szentírás.eu',
            'metaTitle' => 'Találd ki a könyvet - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'books' => $books,
            'verses' => $gameState['verses'] ?? '',
            'guesses' => $guesses,
            'won' => $won,
            'correctBook' => $won ? $gameState['book'] : null
        ]);
    }
}
