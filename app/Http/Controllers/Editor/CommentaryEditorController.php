<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Pagination\Paginator;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\Commentary;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;
use SzentirasHu\Service\Ai\CommentaryService;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Data\Entity\Translation;

class CommentaryEditorController extends Controller
{
    public function __construct(
        protected TranslationService $translationService,
        protected BookService $bookService,
        protected CommentaryService $commentaryService,
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

        // Check if this is a JSON request
        if ($request->expectsJson()) {
            // Call the Artisan command asynchronously
            Artisan::call('szentiras:generate-commentary', [
                'reference' => $request->input('reference'),
                'translation' => $request->input('translation'),
                '--force' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kommentár generálása megkezdődött.',
            ]);
        }

        // Call the Artisan command
        Artisan::call('szentiras:generate-commentary', [
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

    /**
     * Get the status of a commentary by reference and translation.
     */
    public function statusByReference(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
            'translation' => 'required|string',
        ]);

        $reference = $request->input('reference');
        $translationAbbrev = $request->input('translation');

        // Get the translation
        $translation = Translation::where('abbrev', $translationAbbrev)->first();
        if (!$translation) {
            return response()->json(['error' => 'Translation not found'], 404);
        }

        // Parse the reference string
        try {
            $canonicalReference = CanonicalReference::fromString($reference);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid reference format'], 400);
        }

        // Find exact matching commentaries for this reference
        $commentaries = $this->commentaryService->findForReference($canonicalReference, $translation);

        // Filter for exact matches only
        $exactMatch = $commentaries->firstWhere('is_exact', true);

        if (!$exactMatch) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No exact commentary found for this reference',
            ]);
        }

        return response()->json([
            'id' => $exactMatch->id,
            'status' => $exactMatch->status,
            'commentary_text' => $exactMatch->commentary_text,
            'started_at' => $exactMatch->started_at,
            'completed_at' => $exactMatch->completed_at,
            'error_message' => $exactMatch->error_message,
        ]);
    }

    /**
     * Delete a commentary.
     */
    public function destroy(Commentary $commentary)
    {
        $commentary->delete();

        return redirect()->route('editor.commentaries.index')
            ->with('success', 'Kommentár törölve.');
    }
}
