<?php

namespace SzentirasHu\Test\Common;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;

/**
 * Optimized test case for faster database testing.
 *
 * This test case uses DatabaseTransactions instead of RefreshDatabase
 * to avoid rebuilding the database for each test method.
 * Database is seeded once per test class in setUp().
 *
 * For tests that need a fresh database, use RefreshDatabaseTestCase instead.
 */
class FastDatabaseTestCase extends TestCase
{
    use DatabaseTransactions;

    /**
     * Track if database has been seeded for this test class.
     */
    private static $databaseSeeded = false;

    /**
     * Setup before each test runs.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!self::$databaseSeeded) {
            $this->clearCaches();
            $this->seedDatabaseOnce();
            self::$databaseSeeded = true;
        }
    }

    /**
     * Clear caches to ensure clean state.
     */
    protected function clearCaches(): void
    {
        // Clear caches only once per test class
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
    }

    /**
     * Seed the database only once per test class.
     */
    protected function seedDatabaseOnce(): void
    {
        // Seed the database
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        
        // Configure translations
        \Config::set(
            'translations',
            array_merge_recursive(
                \Config::get('translations'),
                [
                    'TESTTRANS' => [
                        'verseTypes' =>
                        [
                            'text' => [6, 901],
                            'heading' => [0 => 5, 1 => 10, 2 => 20, 3 => 30],
                            'footnote' => [120, 2001, 2002],
                            'poemLine' => [902],
                            'xref' => [920]
                        ],
                        'textSource' => env('TEXT_SOURCE_KNB'),
                        'id' => 1001
                    ]
                ]
            )
        );
    }

    /**
     * Clean up after the last test of this class runs.
     */
    public static function tearDownAfterClass(): void
    {
        // Reset the seeded flag for the next test class
        self::$databaseSeeded = false;
        parent::tearDownAfterClass();
    }
}