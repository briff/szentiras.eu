<?php
namespace SzentirasHu\Http\Controllers\Display;

use SzentirasHu\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;

class GreekTextController extends Controller
{
    public function __construct(protected BookService $bookService, protected TranslationService $translationService)
    {
    }

    public function show(?string $bookAbbrev = null)
    {   
        $templateTranslation = 7;
        $books = $this->bookService->getBooksForTranslation($this->translationService->getById($templateTranslation));
        $translation = new Translation();
        $translation->abbrev = 'GNT';
        
        $book = null;
        if ($bookAbbrev) {
            $book = collect($books)->firstWhere('abbrev', $bookAbbrev);
            $greekVerses = $book
            ? GreekVerse::where('usx_code', $book->usx_code)
                ->orderBy('chapter')
                ->orderBy('verse')
                ->get()
            : collect();
        }  else {
            $greekVerses = collect();
        }
    
        return view('greekText.gnt', [ 'translation' => $translation, 'books' => $books, 'greekVerses' => $greekVerses, 'book' => $book ]);
    }
}
