<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\OnlineMemoryGameService;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for Online Memory Game tool
 */
class OnlineMemoryGameController extends Controller
{
    public function __construct(
        protected OnlineMemoryGameService $onlineMemoryGameService,
        protected ToolsService $toolsService
    ) {
    }

    public function index(Request $request)
    {
        $translations = $this->toolsService->getAllTranslations();
        $selectedTranslation = null;
        $cards = [];
        $errors = [];
        
        // Set default translation
        if (!$request->isMethod('post') || !$request->has('translation_abbrev')) {
            $defaultTranslation = $this->toolsService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        } else {
            $selectedTranslation = $request->input('translation_abbrev');
        }
        
        // Generate cards on POST
        if ($request->isMethod('post') && $request->input('action') === 'generate') {
            $rows = (int)$request->input('rows', 2);
            $cols = (int)$request->input('cols', 3);
            
            $translation = $this->toolsService->getTranslationByAbbreviation($selectedTranslation);
            $books = $this->toolsService->getBooksForTranslation($translation);
            
            $result = $this->onlineMemoryGameService->generateCards($rows, $cols, $translation, $books);
            $cards = $result['cards'];
            $errors = $result['errors'];
        }
        
        return \View::make("tools/memory-game-play", [
            'pageTitle' => 'Online memóriajáték - Szentírás.eu',
            'metaTitle' => 'Online memóriajáték - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'cards' => $cards,
            'errors' => $errors,
            'rows' => $request->input('rows', 2),
            'cols' => $request->input('cols', 3)
        ]);
    }
}
