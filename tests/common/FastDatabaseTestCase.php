<?php

namespace SzentirasHu\Test\Common;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;

/**
 * Optimized test case for faster database testing.
 *
 * Uses DatabaseTransactions instead of RefreshDatabase so the schema is not
 * rebuilt for every test method. Because each test runs inside its own
 * transaction that is rolled back afterwards, the seed must run for every test
 * (not once per class) — otherwise only the first test of a class would see the
 * TESTTRANS translation, book and verse rows.
 *
 * For tests that need a fresh database, use RefreshDatabaseTestCase instead.
 */
class FastDatabaseTestCase extends TestCase
{
    use DatabaseTransactions;

    /**
     * Track whether the on-disk framework caches have been cleared for this run.
     */
    private static bool $cachesCleared = false;

    /**
     * Setup before each test runs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$cachesCleared) {
            $this->clearCaches();
            self::$cachesCleared = true;
        }

        $this->seedTestData();
    }

    /**
     * Clear the framework's on-disk caches once to ensure a clean state.
     */
    protected function clearCaches(): void
    {
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
    }

    /**
     * Seed the database and register the TESTTRANS translation definition.
     *
     * Runs for every test: the surrounding transaction is rolled back between
     * tests, so the seeded rows would otherwise be gone for the second test
     * onwards. The translation is registered under `translations.definitions`
     * (keyed by abbrev) and `translations.ids` (id => abbrev), matching the
     * structure the verse parser and type map read from.
     */
    protected function seedTestData(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $translations = \Config::get('translations');
        $translations['definitions']['TESTTRANS'] = [
            'verseTypes' => [
                'text' => [6, 901],
                'heading' => [5 => 0, 10 => 1, 20 => 2, 30 => 3],
                'footnote' => [120, 2001, 2002],
                'poemLine' => [902],
                'xref' => [920],
            ],
            'textSource' => env('TEXT_SOURCE_KNB'),
            'id' => 1001,
        ];
        $translations['ids'][1001] = 'TESTTRANS';
        \Config::set('translations', $translations);
    }

    /**
     * Clean up after the last test of this class runs.
     */
    public static function tearDownAfterClass(): void
    {
        self::$cachesCleared = false;
        parent::tearDownAfterClass();
    }
}
