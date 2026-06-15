<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Ai\StrongWordTranslationService;

class StrongWordEditorController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(
        protected StrongWordTranslationService $translationService,
    ) {}

    /**
     * Display a paginated, searchable listing of Strong words.
     */
    public function index(Request $request): View
    {
        $filter = trim((string) $request->query('q', ''));

        return view('editor.strongWords.index', [
            'strongWords' => $this->paginateStrongWords($filter),
            'filter' => $filter,
        ]);
    }

    /**
     * Display a single Strong word's dictionary entry for editing.
     */
    public function show(StrongWord $strongWord): View
    {
        $strongWord->load(['dictionaryEntry', 'dictionaryMeanings' => fn ($query) => $query->orderBy('order')]);

        return view('editor.strongWords.show', [
            'strongWord' => $strongWord,
        ]);
    }

    /**
     * Save manual edits to a Strong word's dictionary entry and meanings.
     */
    public function update(Request $request, StrongWord $strongWord): RedirectResponse
    {
        $validated = $request->validate([
            'paradigm' => 'nullable|string',
            'etymology' => 'nullable|string',
            'notes' => 'nullable|string',
            'meanings' => 'nullable|array',
            'meanings.*.meaning' => 'required_with:meanings|string',
            'meanings.*.explanation' => 'nullable|string',
        ]);

        $this->translationService->persist($strongWord->number, [
            'word' => $validated['paradigm'] ?? '',
            'etymology' => $validated['etymology'] ?? '',
            'notes' => $validated['notes'] ?? null,
            'meanings' => array_values($validated['meanings'] ?? []),
        ], 'editor');

        return redirect()->route('editor.strongWords.show', $strongWord)
            ->with('success', 'A szótári szócikk mentve.');
    }

    /**
     * Generate a dictionary entry using the OpenAI flow and return it as a preview.
     * Nothing is persisted; the editor reviews and saves it via update().
     */
    public function generate(StrongWord $strongWord): JsonResponse
    {
        try {
            $result = $this->translationService->generate($strongWord);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'A generálás nem sikerült: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'translation' => $result['translation'],
            'tokenUsage' => $result['tokenUsage'],
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, StrongWord>
     */
    private function paginateStrongWords(string $filter): LengthAwarePaginator
    {
        return $this->queryStrongWords($filter)
            ->paginate(self::PAGE_SIZE)
            ->appends(['q' => $filter]);
    }

    /**
     * @return Builder<StrongWord>
     */
    private function queryStrongWords(string $filter): Builder
    {
        $query = StrongWord::query()
            ->whereNotNull('transliteration')
            ->where('transliteration', '!=', '')
            ->where(function (Builder $q): void {
                $q->has('greekVerses')
                    ->orHas('dictionaryMeanings');
            })
            ->with(['dictionaryEntry', 'dictionaryMeanings' => fn ($q) => $q->orderBy('order')])
            ->withCount('greekVerses')
            ->orderBy('normalized');

        if ($filter !== '') {
            $normalized = mb_strtolower($filter);
            $query->where(function (Builder $q) use ($normalized, $filter): void {
                $q->where('normalized', 'like', "{$normalized}%")
                    ->orWhere('transliteration', 'like', "{$normalized}%")
                    ->orWhere('number', $filter)
                    ->orWhereHas('dictionaryMeanings', function (Builder $meaning) use ($filter): void {
                        $meaning->where('meaning', 'ilike', "%{$filter}%");
                    });
            });
        }

        return $query;
    }
}
