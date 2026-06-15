<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('cache:clear');
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_sitemap_is_served_as_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
    }

    public function test_sitemap_lists_chapter_urls_for_enabled_translations(): void
    {
        $response = $this->get('/sitemap.xml');

        // Translation root and chapter URLs (Ter/GEN has verses up to chapter 50, Kiv/EXO up to 3).
        $response->assertSee('<loc>' . url('/TESTTRANS') . '</loc>', false);
        $response->assertSee('<loc>' . url('/TESTTRANS/Ter2') . '</loc>', false);
        $response->assertSee('<loc>' . url('/TESTTRANS/Ter50') . '</loc>', false);
        $response->assertSee('<loc>' . url('/TESTTRANS/Kiv1') . '</loc>', false);
    }

    public function test_sitemap_omits_books_without_verses(): void
    {
        $response = $this->get('/sitemap.xml');

        // Lev/Szám have no seeded verses, so they get no chapter URLs.
        $response->assertDontSee('<loc>' . url('/TESTTRANS/Lev1') . '</loc>', false);
    }

    public function test_sitemap_includes_gnt_chapters_from_greek_verses(): void
    {
        // GNT (inserted by migration) stores its chapters in greek_verses.
        $gnt = Translation::where('abbrev', 'GNT')->firstOrFail();
        $this->createGreekBook($gnt->id);
        Config::set('settings.enabledTranslations', array_merge(
            Config::get('settings.enabledTranslations'),
            [$gnt->id]
        ));
        Cache::flush();

        $response = $this->get('/sitemap.xml');

        $response->assertSee('<loc>' . url('/GNT') . '</loc>', false);
        $response->assertSee('<loc>' . url('/GNT/Mt1') . '</loc>', false);
        $response->assertSee('<loc>' . url('/GNT/Mt3') . '</loc>', false);
    }

    private function createGreekBook(int $translationId): void
    {
        $book = new Book();
        $book->translation_id = $translationId;
        $book->name = 'Evangélium Máté szerint';
        $book->abbrev = 'Mt';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->order = 1;
        $book->usx_code = 'MAT';
        $book->save();

        foreach ([1, 2, 3] as $chapter) {
            $greekVerse = new GreekVerse();
            $greekVerse->source = 'test';
            $greekVerse->gepi = "MAT.{$chapter}.1";
            $greekVerse->usx_code = 'MAT';
            $greekVerse->chapter = $chapter;
            $greekVerse->verse = 1;
            $greekVerse->text = "Greek chapter {$chapter} verse 1";
            $greekVerse->json = '[]';
            $greekVerse->strongs = '';
            $greekVerse->strong_transliterations = '';
            $greekVerse->strong_normalizations = '';
            $greekVerse->save();
        }
    }
}
