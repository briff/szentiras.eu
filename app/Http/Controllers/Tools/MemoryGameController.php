<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\MemoryGameService;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for Memory Game Creator tool
 */
class MemoryGameController extends Controller
{
    public function __construct(
        protected MemoryGameService $memoryGameService,
        protected ToolsService $toolsService
    ) {
    }

    public function index(Request $request)
    {
        $verses = [];
        $errors = [];
        $input = '';
        $selectedTranslation = null;

        // Get all translations for the dropdown
        $translations = $this->toolsService->getAllTranslations();
        
        // Set default translation for initial page load
        if (!$request->isMethod('post')) {
            $defaultTranslation = $this->toolsService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        }

        if ($request->isMethod('post')) {
            $input = $request->input('references', '');
            $translationAbbrev = $request->input('translation_abbrev', null);
            
            // Get the selected translation or use default
            if ($translationAbbrev) {
                $translation = $this->toolsService->getTranslationByAbbreviation($translationAbbrev);
                $selectedTranslation = $translationAbbrev;
            } else {
                $translation = $this->toolsService->getDefaultTranslation();
                $selectedTranslation = $translation->abbrev;
            }
            
            $result = $this->memoryGameService->processReferences($input, $translation);
            $verses = $result['verses'];
            $errors = $result['errors'];
        }

        return \View::make("tools/memory-game-creator", [
            'pageTitle' => 'Memóriajáték készítő - Szentírás.eu',
            'metaTitle' => 'Memóriajáték készítő - Szentírás.eu',
            'verses' => $verses,
            'errors' => $errors,
            'input' => $input,
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation
        ]);
    }
}
