<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;
use SzentirasHu\Data\Entity\EmbeddedExcerpt;
use SzentirasHu\Data\Entity\EmbeddedExcerptScope;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\GreekVerseEmbedding;
use SzentirasHu\Service\Search\SemanticSearchService;

class ThemeController extends Controller
{
    private const THEME_CACHE_TAG = 'theme_verses';

    public function __construct(
        protected SemanticSearchService $semanticSearchService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $themes = Theme::latest()->paginate(20);

        return view('editor.themes.index', [
            'themes' => $themes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('editor.themes.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'hungarian_keyword' => 'required|string|max:255|unique:themes,hungarian_keyword',
            'photo_keywords' => 'nullable|string',
        ]);

        // Generate embedding vector
        $embeddingResult = $this->semanticSearchService->generateVector($validated['hungarian_keyword']);
        $vector = new Vector($embeddingResult->vector);

        $theme = Theme::create([
            'hungarian_keyword' => $validated['hungarian_keyword'],
            'embedding' => $vector,
            'photo_keywords' => $validated['photo_keywords'] ?? null,
        ]);

        return redirect()->route('editor.themes.show', $theme)
            ->with('success', 'Téma sikeresen létrehozva.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Theme $theme)
    {
        // Get available translations for dropdown (hardcoded for now)
        $translations = [
            'SZIT' => 'SZIT',
            'KNB' => 'KNB',
            'RUF' => 'RUF',
            'KG' => 'KG',
            
        ];

        // Determine selected translation from request or default
        $selectedTranslation = request()->input('translation', 'SZIT');
        $limit = 10;

        // Cache key for verse results
        $cacheKey = "theme_{$theme->id}_verses_{$selectedTranslation}_{$limit}";
        $verses = Cache::tags([self::THEME_CACHE_TAG, "theme_{$theme->id}"])->remember($cacheKey, now()->addHours(24), function () use ($theme, $selectedTranslation, $limit) {
            return $this->semanticSearchService->findClosestVersesForTheme(
                $theme,
                $selectedTranslation,
                $limit
            );
        });

        return view('editor.themes.show', [
            'theme' => $theme,
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'verses' => $verses,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Theme $theme)
    {
        return view('editor.themes.edit', [
            'theme' => $theme,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Theme $theme)
    {
        $validated = $request->validate([
            'hungarian_keyword' => 'required|string|max:255|unique:themes,hungarian_keyword,' . $theme->id,
            'photo_keywords' => 'nullable|string',
        ]);

        $updateData = ['photo_keywords' => $validated['photo_keywords'] ?? null];

        // Regenerate embedding only if Hungarian keyword changed
        if ($theme->hungarian_keyword !== $validated['hungarian_keyword']) {
            $embeddingResult = $this->semanticSearchService->generateVector($validated['hungarian_keyword']);
            $vector = new Vector($embeddingResult->vector);
            $updateData['embedding'] = $vector;
            $updateData['hungarian_keyword'] = $validated['hungarian_keyword'];
        }

        $theme->update($updateData);

        // Clear cached verse results for this theme using tags
        Cache::tags(["theme_{$theme->id}"])->flush();

        return redirect()->route('editor.themes.show', $theme)
            ->with('success', 'Téma sikeresen frissítve.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Theme $theme)
    {
        $theme->delete();

        return redirect()->route('editor.themes.index')
            ->with('success', 'Téma sikeresen törölve.');
    }

    /**
     * Find related themes based on gepi similarity search.
     * If multiple gepis are provided, calculates the average embedding vector.
     */
    public function testSimilarity(Request $request)
    {
        $validated = $request->validate([
            'gepis' => 'required|string',
            'limit' => 'integer|min:1|max:50',
        ]);

        $limit = $validated['limit'] ?? 10;
        $gepis = array_filter(array_map('trim', explode(',', $validated['gepis'])));

        if (empty($gepis)) {
            return response()->json([
                'error' => 'No valid gepis provided',
                'results' => [],
            ], 400);
        }

        $model = config('settings.ai.embeddingModel');
        
        // Fetch all embeddings for the provided gepis
        $embeddings = EmbeddedExcerpt::query()
            ->whereIn('gepi', $gepis)
            ->where('model', $model)
            ->get();

        $foundGepis = $embeddings->pluck('gepi')->toArray();
        $notFoundGepis = array_diff($gepis, $foundGepis);

        if ($embeddings->isEmpty()) {
            return response()->json([
                'error' => 'No valid embeddings found for provided gepis',
                'results' => [],
            ], 400);
        }

        $vectorDimensions = count($embeddings->first()->embedding->toArray());
        $sum = array_fill(0, $vectorDimensions, 0.0);

        $count = $embeddings->count();

        foreach ($embeddings as $embedding) {
            $v = $embedding->embedding->toArray();
            foreach ($v as $i => $x) {
                $sum[$i] += (float)$x;
            }
        }

        // Mean
        $avg = array_map(fn($x) => $x / $count, $sum);

        // L2 normalize centroid (important for cosine)
        $normSq = 0.0;
        foreach ($avg as $x) {
            $normSq += $x * $x;
        }
        $norm = sqrt($normSq);

        if ($norm > 0.0) {
            $avg = array_map(fn($x) => $x / $norm, $avg);
        }

        $centroid = new Vector($avg);
        // Find similar themes using the average embedding vector
        $similarThemes = Theme::query()
            ->nearestNeighbors('embedding', $centroid, Distance::Cosine)
            ->limit($limit)
            ->get()
            ->map(function (Theme $theme) {
                return [
                    'id' => $theme->id,
                    'hungarian_keyword' => $theme->hungarian_keyword,
                    'photo_keywords' => $theme->photo_keywords,
                    'similarity' => is_nan($theme->getAttribute('neighbor_distance')) ? 0 : 1 - $theme->getAttribute('neighbor_distance'),
                ];
            });

        $results = [
            'found' => true,
            'gepis_count' => $count,
            'gepis_found' => $foundGepis,
            'gepis_not_found' => $notFoundGepis,
            'themes' => $similarThemes,
        ];

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}
