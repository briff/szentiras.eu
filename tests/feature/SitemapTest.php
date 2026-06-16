<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\ReadingPlan;
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

        @unlink(public_path('sitemap.xml'));
    }

    protected function tearDown(): void
    {
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
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

    public function test_sitemap_includes_static_landing_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertSee('<loc>' . url('/info') . '</loc>', false);
        $response->assertSee('<loc>' . url('/forditasok') . '</loc>', false);
        $response->assertSee('<loc>' . url('/tervek') . '</loc>', false);
        $response->assertSee('<loc>' . url('/tools') . '</loc>', false);
        $response->assertSee('<loc>' . url('/rolunk') . '</loc>', false);
    }

    public function test_sitemap_omits_individual_reading_plans_and_their_days(): void
    {
        // The migration seeds reading plan id 1 ("365 napos terv") with day rows.
        // These pages only link to other pages, so they are excluded from the sitemap.
        $readingPlan = ReadingPlan::with('days')->findOrFail(1);
        $firstDay = $readingPlan->days->first();

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee('<loc>' . url("/tervek/{$readingPlan->id}") . '</loc>', false);
        $response->assertDontSee('<loc>' . url("/tervek/{$readingPlan->id}/{$firstDay->day_number}") . '</loc>', false);
    }

    public function test_sitemap_includes_gnt_chapters_from_greek_verses(): void
    {
        // GNT (inserted by migration) has no books of its own: it reuses the
        // template translation's book list and pulls chapters from greek_verses.
        $gnt = Translation::where('abbrev', 'GNT')->firstOrFail();
        $this->createGreekBook();
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

    public function test_generate_command_writes_static_sitemap_file(): void
    {
        $path = public_path('sitemap.xml');

        $exitCode = Artisan::call('szentiras:generate-sitemap');

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('<urlset', $contents);
        $this->assertStringContainsString('<loc>' . url('/TESTTRANS/Ter50') . '</loc>', $contents);
    }

    public function test_generated_sitemap_uses_configured_app_url_not_localhost(): void
    {
        // Mirror how the console bootstrap (SetRequestForConsole) seeds url()'s
        // root from config('app.url') when generating the sitemap offline.
        Config::set('app.url', 'https://szentiras.hu');
        $this->app->instance('request', Request::create('https://szentiras.hu', 'GET'));
        Cache::flush();

        $exitCode = Artisan::call('szentiras:generate-sitemap');

        $this->assertSame(0, $exitCode);

        $contents = (string) file_get_contents(public_path('sitemap.xml'));
        $this->assertStringContainsString('<loc>https://szentiras.hu/TESTTRANS/Ter50</loc>', $contents);
        $this->assertStringNotContainsString('localhost', $contents);
    }

    public function test_sitemap_route_serves_pregenerated_static_file_when_present(): void
    {
        file_put_contents(public_path('sitemap.xml'), '<?xml version="1.0"?><urlset>pregenerated</urlset>');

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('pregenerated', false);
    }

    private function createGreekBook(): void
    {
        $templateTranslationId = 7;
        if (Translation::find($templateTranslationId) === null) {
            $template = new Translation();
            $template->id = $templateTranslationId;
            $template->name = 'GNT template translation';
            $template->abbrev = 'GNTTPL';
            $template->denom = 'denom';
            $template->lang = 'hu';
            $template->save();
        }

        $book = new Book();
        $book->translation_id = $templateTranslationId;
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
