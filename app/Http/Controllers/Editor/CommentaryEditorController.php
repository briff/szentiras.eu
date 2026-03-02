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

        // Add reference field from metadata if available
        if (!isset($commentaryData['reference']) && $commentary->metadata && isset($commentary->metadata['reference'])) {
            $commentaryData['reference'] = $commentary->metadata['reference'];
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
     * Update a commentary's verification level or text content.
     */
    public function update(Request $request, Commentary $commentary)
    {
        // Check what type of update is being requested
        if ($request->has('verification_level')) {
            $request->validate([
                'verification_level' => 'required|string|in:none,sanity,theology',
            ]);

            $commentary->update([
                'verification_level' => $request->input('verification_level'),
            ]);

            return redirect()->route('editor.commentaries.show', $commentary)
                ->with('success', 'Ellenőrzési szint frissítve.');
        }
        
        // Handle commentary text and references update
        if ($request->has('commentary_text') || $request->has('references_form_submitted')) {
            $request->validate([
                'commentary_text' => 'nullable|string',
                'references' => 'nullable|array',
                'references.*.ref' => 'required_with:references|string',
                'references.*.reason' => 'nullable|string',
            ]);
            
            // Parse existing commentary data
            $commentaryData = json_decode($commentary->commentary_text, true);
            if (!is_array($commentaryData)) {
                $commentaryData = [
                    'commentary_text' => $commentary->commentary_text,
                    'references' => [],
                ];
            }
            
            // Update commentary text if provided
            if ($request->has('commentary_text')) {
                $commentaryData['commentary_text'] = $request->input('commentary_text');
            }
            
            // Update references if the references form was submitted
            if ($request->has('references_form_submitted')) {
                // If references field is present, use it; otherwise set to empty array (user cleared all)
                $commentaryData['references'] = $request->has('references') ? $request->input('references') : [];
            }
            
            // Save updated commentary text as JSON
            $commentary->update([
                'commentary_text' => json_encode($commentaryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);
            
            // Determine appropriate success message
            if ($request->has('commentary_text') && !$request->has('references_form_submitted')) {
                $message = 'Kommentár szövege frissítve.';
            } elseif (!$request->has('commentary_text') && $request->has('references_form_submitted')) {
                $message = 'Hivatkozások frissítve.';
            } else {
                $message = 'Kommentár szövege és hivatkozások frissítve.';
            }
            
            return redirect()->route('editor.commentaries.show', $commentary)
                ->with('success', $message);
        }
        
        // If no valid update fields provided, redirect back with error
        return redirect()->route('editor.commentaries.show', $commentary)
            ->with('error', 'Érvénytelen frissítési kérés.');
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
