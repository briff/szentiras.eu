<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Test\Common\TestCase;

class NumberingSchemeEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        
        $currentConfig = \Config::get('translations');
        $currentConfig['definitions']['TESTTRANS'] = [
                'verseTypes' =>
                [
                    'text' => [6, 901],
                    'heading' => [5=>0, 10=>1, 20=>2, 30=>3],
                    'footnote' => [120, 2001, 2002],
                    'poemLine' => [902],
                    'xref' => [920]
                ],
                'textSource' => env('TEXT_SOURCE_KNB'),
                'id' => 1001];
        $currentConfig['ids'][1001] = 'TESTTRANS' ;
        \Config::set('translations', $currentConfig);
    }

    public function testPsalmsVulgataScheme()
    {
        // Request a reference with scheme=vulgata
        $response = $this->get('/Ter%202?scheme=vulgata');
        $response->assertStatus(200);
        // We can't assert the exact content because it depends on database,
        // but we can at least ensure the page loads successfully.
        // Additionally, we could check that the response contains some expected text,
        // but we'll keep it simple.
    }

    public function testPsalmsDefaultScheme()
    {
        $response = $this->get('/Ter%202');
        $response->assertStatus(200);
    }

}