<?php

namespace SzentirasHu\Http\Controllers\Display;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\StrongWord;

class GreekDictionaryController extends Controller
{
    private const PAGE_SIZE = 20;

    public function index(Request $request): View
    {
        $filter = $this->resolveFilter($request);

        return view('greekText.dictionary', [
            'strongWords' => $this->paginateStrongWords($filter),
            'filter' => $filter,
            'teaser' => 'Az Újszövetség összes görög (Strong) szava betűrendben: görög–magyar szótár, latin átírással és újszövetségi előfordulásokkal.',
        ]);
    }

    public function filter(Request $request): View
    {
        $filter = $this->resolveFilter($request);

        return view('greekText.dictionaryRows', [
            'strongWords' => $this->paginateStrongWords($filter),
            'filter' => $filter,
        ]);
    }

    private function resolveFilter(Request $request): string
    {
        return trim((string) $request->query('q', ''));
    }

    /**
     * @return LengthAwarePaginator<int, StrongWord>
     */
    private function paginateStrongWords(string $filter): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);
        $cacheKey = 'greek_dict_' . $page . '_' . md5($filter);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($filter) {
            return $this->queryStrongWords($filter)
                ->paginate(self::PAGE_SIZE)
                ->appends(['q' => $filter]);
        });
    }

    /**
     * @return Builder<StrongWord>
     */
    private function queryStrongWords(string $filter = ''): Builder
    {
        $query = StrongWord::query()
            ->whereNotNull('transliteration')
            ->where('transliteration', '!=', '')
            ->where(function (Builder $q): void {
                $q->has('greekVerses')
                    ->orHas('dictionaryMeanings');
            })
            ->with('dictionaryMeanings')
            ->withCount('greekVerses')
            ->orderBy('normalized');

        if ($filter !== '') {
            $normalized = mb_strtolower($filter);
            $query->where(function (Builder $q) use ($normalized, $filter): void {
                $q->where('normalized', 'like', "{$normalized}%")
                    ->orWhere('transliteration', 'like', "{$normalized}%")
                    ->orWhereHas('dictionaryMeanings', function (Builder $meaning) use ($filter): void {
                        $meaning->where('meaning', 'ilike', "%{$filter}%");
                    });
            });
        }

        return $query;
    }
}
