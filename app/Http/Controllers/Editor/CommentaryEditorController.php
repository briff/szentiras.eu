<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Pagination\Paginator;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;

class CommentaryEditorController extends Controller
{
    public function __construct(
        protected TranslationService $translationService,
        protected BookService $bookService,
    ) {}

    /**
     * Display a listing of commentaries.
     */
    public function index()
    {
        $commentaries = Commentary::query()
            ->with(['translation', 'ranges'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $translations = $this->translationService->getAllTranslations();

        return view('editor.commentaries.index', [
            'commentaries' => $commentaries,
            'translations' => $translations,
        ]);
    }

    /**
     * Display a specific commentary for editing.
     */
    public function show(Commentary $commentary)
    {
        $commentary->load(['translation', 'ranges']);

        // Get book information for display
        $book = $this->bookService->getBookByUsxCodeTranslation(
            $commentary->usx_code,
            $commentary->translation->abbrev
        );

        // Format ranges for display
        $formattedRanges = $commentary->ranges->map(function ($range) {
            return "{$range->start_chapter}:{$range->start_verse} - {$range->end_chapter}:{$range->end_verse}";
        })->implode(', ');

        // Parse commentary text JSON
        $commentaryData = json_decode($commentary->commentary_text, true);
        if (!is_array($commentaryData)) {
            $commentaryData = [
                'commentary_text' => $commentary->commentary_text,
                'references' => [],
            ];
        }

        return view('editor.commentaries.show', [
            'commentary' => $commentary,
            'commentaryData' => $commentaryData,
            'book' => $book,
            'formattedRanges' => $formattedRanges,
        ]);
    }

    /**
     * Generate AI commentary for a reference.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
            'translation' => 'required|string',
        ]);

        // Call the Artisan command
        Artisan::call('ai:generate-commentary', [
            'reference' => $request->input('reference'),
            'translation' => $request->input('translation'),
            '--force' => true,
        ]);

        return redirect()->back()->with('success', 'Kommentár generálva.');
    }

    /**
     * Get the status of a commentary.
     */
    public function status(Commentary $commentary)
    {
        return response()->json([
            'id' => $commentary->id,
            'status' => $commentary->status,
            'commentary_text' => $commentary->commentary_text,
            'started_at' => $commentary->started_at,
            'completed_at' => $commentary->completed_at,
            'error_message' => $commentary->error_message,
        ]);
    }
}
