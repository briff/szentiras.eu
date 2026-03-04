<?php

namespace SzentirasHu\Service\Theme;

use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;
use SzentirasHu\Data\Entity\EmbeddedExcerpt;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;
use Illuminate\Support\Facades\Config;

class ThemeService
{
    public function __construct(
        protected TextService $textService,
        protected TranslationService $translationService,
    ) {}

    /**
     * Find themes similar to a given Bible reference.
     *
     * @param string $reference Reference string (e.g., "Jn 3:16")
     * @param string $translationAbbrev Translation abbreviation (e.g., "SZIT")
     * @param int $limit Maximum number of themes to return
     * @return \Illuminate\Support\Collection
     */
    public function findSimilarThemes(string $reference, string $translationAbbrev, int $limit = 10): Collection
    {
        // 1. Get translation
        $translation = $this->translationService->getByAbbreviation($translationAbbrev);

        // 2. Parse reference and get verses
        $canonicalRef = CanonicalReference::fromString($reference, $translation->id);
        $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);

        // 3. Extract gepi values from all verse data
        $gepis = [];
        foreach ($verseContainers as $verseContainer) {
            foreach ($verseContainer->getParsedVerses() as $verseData) {
                if (!empty($verseData->gepi)) {
                    $gepis[] = $verseData->gepi;
                }
            }
        }

        if (empty($gepis)) {
            return collect();
        }

        // 4. Compute centroid vector from embeddings of those gepis
        $centroid = $this->computeCentroid($gepis, $translation->abbrev);
        if (!$centroid) {
            return collect();
        }

        // 5. Find nearest themes using cosine distance
        $similarThemes = Theme::query()
            ->nearestNeighbors('embedding', $centroid, Distance::Cosine)
            ->limit($limit)
            ->get()
            ->map(function (Theme $theme) {
                $distance = $theme->getAttribute('neighbor_distance');
                $similarity = is_nan($distance) ? 0.0 : 1 - (float) $distance;
                return [
                    'id' => $theme->id,
                    'hungarian_keyword' => $theme->hungarian_keyword,
                    'photo_keywords' => $theme->photo_keywords,
                    'similarity' => $similarity,
                ];
            });

        return $similarThemes;
    }

    /**
     * Compute the average embedding vector (centroid) for a list of gepis.
     * Returns a Vector or null if no embeddings found.
     *
     * @param string[] $gepis
     * @param string $translationAbbrev
     * @return Vector|null
     */
    private function computeCentroid(array $gepis, string $translationAbbrev): ?Vector
    {
        $model = Config::get('settings.ai.embeddingModel');
        if (!$model) {
            return null;
        }

        $embeddings = EmbeddedExcerpt::query()
            ->whereIn('gepi', $gepis)
            ->where('model', $model)
            ->where('translation_abbrev', $translationAbbrev)
            ->get();

        if ($embeddings->isEmpty()) {
            return null;
        }

        $firstVector = $embeddings->first()->embedding->toArray();
        $dimensions = count($firstVector);
        $sum = array_fill(0, $dimensions, 0.0);

        foreach ($embeddings as $embedding) {
            $v = $embedding->embedding->toArray();
            foreach ($v as $i => $x) {
                $sum[$i] += (float) $x;
            }
        }

        $count = $embeddings->count();
        $avg = array_map(fn($x) => $x / $count, $sum);

        // L2 normalize centroid (important for cosine similarity)
        $normSq = 0.0;
        foreach ($avg as $x) {
            $normSq += $x * $x;
        }
        $norm = sqrt($normSq);

        if ($norm > 0.0) {
            $avg = array_map(fn($x) => $x / $norm, $avg);
        }

        return new Vector($avg);
    }
}