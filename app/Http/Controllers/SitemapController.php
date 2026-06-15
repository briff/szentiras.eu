<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Repository\ReadingPlanRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Text\BookService;

class SitemapController extends Controller
{
    /**
     * The GNT (Greek) translation has no books of its own; it reuses the book
     * list of this template translation, matching GreekTextController.
     */
    private const GNT_TEMPLATE_TRANSLATION_ID = 7;

    /**
     * Static, crawlable landing pages with no model-derived parameters.
     *
     * @var list<string>
     */
    private const STATIC_PAGES = [
        '/',
        '/info',
        '/impresszum',
        '/informaciok',
        '/kereses',
        '/ai-search',
        '/forditasok',
        '/hang',
        '/tervek',
        '/tools',
        '/tools/memorygame',
        '/tools/guessbook',
        '/tools/memory-game-play',
        '/tools/guess-word',
        '/tools/verse-scramble',
        '/tools/word-from-next-verse',
        '/contact',
    ];

    public function __construct(
        protected TranslationRepository $translationRepository,
        protected BookService $bookService,
        protected ReadingPlanRepository $readingPlanRepository
    ) {
    }

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addDay(), function (): string {
            return $this->buildSitemap();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function buildSitemap(): string
    {
        $locations = self::STATIC_PAGES;

        foreach ($this->readingPlanLocations() as $location) {
            $locations[] = $location;
        }

        foreach ($this->translationRepository->getAll() as $translation) {
            $locations[] = "/{$translation->abbrev}";
            foreach ($this->booksFor($translation) as $book) {
                foreach ($this->chaptersFor($translation, $book) as $chapter) {
                    $locations[] = "/{$translation->abbrev}/{$book->abbrev}{$chapter}";
                }
            }
        }

        $urls = '';
        foreach ($locations as $location) {
            $urls .= '  <url><loc>' . htmlspecialchars($this->absoluteUrl($location), ENT_XML1) . "</loc></url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $urls
            . '</urlset>' . "\n";
    }

    /**
     * Each reading plan's overview page plus a page per day, matching the
     * /tervek/{id} and /tervek/{plan_id}/{day_number} routes.
     *
     * @return list<string>
     */
    private function readingPlanLocations(): array
    {
        $locations = [];
        foreach ($this->readingPlanRepository->getAll() as $readingPlan) {
            $locations[] = "/tervek/{$readingPlan->id}";
            foreach ($readingPlan->days as $day) {
                $locations[] = "/tervek/{$readingPlan->id}/{$day->day_number}";
            }
        }

        return $locations;
    }

    /**
     * The books to list for a translation. GNT stores no books of its own, so
     * it borrows the template translation's books (same as GreekTextController).
     *
     * @return iterable<Book>
     */
    private function booksFor($translation): iterable
    {
        if ($translation->abbrev === 'GNT') {
            $template = $this->translationRepository->getById(self::GNT_TEMPLATE_TRANSLATION_ID);

            return $this->bookService->getBooksForTranslation($template);
        }

        return $this->translationRepository->getBooks($translation);
    }

    /**
     * The chapter numbers that have text for a given book. The GNT (Greek)
     * translation stores its text in the greek_verses table rather than verses,
     * so it needs a dedicated lookup.
     *
     * @return int[]
     */
    private function chaptersFor($translation, $book): array
    {
        if ($translation->abbrev === 'GNT') {
            return GreekVerse::where('usx_code', $book->usx_code)
                ->distinct()
                ->orderBy('chapter')
                ->pluck('chapter')
                ->map(fn ($chapter) => (int) $chapter)
                ->all();
        }

        $chapterCount = (int) $this->bookService->getChapterCount($book, $translation);

        return $chapterCount > 0 ? range(1, $chapterCount) : [];
    }

    /**
     * Build a spec-compliant absolute URL, percent-encoding non-ASCII path
     * segments (e.g. book abbreviations like "Szám").
     */
    private function absoluteUrl(string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));

        return url('/' . $encoded);
    }
}
