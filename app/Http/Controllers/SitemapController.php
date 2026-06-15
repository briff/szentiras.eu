<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Text\BookService;

class SitemapController extends Controller
{
    public function __construct(
        protected TranslationRepository $translationRepository,
        protected BookService $bookService
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
        $locations = ['/'];
        foreach ($this->translationRepository->getAll() as $translation) {
            $locations[] = "/{$translation->abbrev}";
            foreach ($this->translationRepository->getBooks($translation) as $book) {
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
