<?php

namespace Tests\Feature\Jobs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Jobs\SearchAndPrepareCandidates;
use SzentirasHu\Services\PixabayClient;
use SzentirasHu\Test\Common\TestCase;

class SearchAndPrepareCandidatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_handles_expired_session()
    {
        $session = VerseCardSession::factory()->create([
            'expires_at' => Carbon::now()->subHour(),
            'status' => 'searching',
        ]);

        $mockClient = Mockery::mock(PixabayClient::class);
        $job = new SearchAndPrepareCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        $this->assertEquals('expired', $session->status);
        $this->assertCount(0, $session->assets);
    }

    /** @test */
    public function it_searches_pixabay_and_creates_candidate_assets()
    {
        $session = VerseCardSession::factory()->create([
            'keywords' => ['peace', 'love'],
            'theme_slug' => 'nature',
            'status' => 'searching',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        // Mock PixabayClient
        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->with([
                'q' => 'nature peace love',
                'page' => 1,
                'safesearch' => true,
                'image_type' => 'photo',
                'orientation' => 'horizontal',
                'per_page' => 50,
                'order' => 'popular',
            ])
            ->andReturn([
                'hits' => [
                    [
                        'id' => 12345,
                        'user' => 'artist1',
                        'pageURL' => 'https://pixabay.com/photo/12345',
                        'largeImageURL' => 'https://pixabay.com/large/12345.jpg',
                    ],
                    [
                        'id' => 12346,
                        'user' => 'artist2',
                        'pageURL' => 'https://pixabay.com/photo/12346',
                        'largeImageURL' => 'https://pixabay.com/large/12346.jpg',
                    ],
                    [
                        'id' => 12347,
                        'user' => 'artist3',
                        'pageURL' => 'https://pixabay.com/photo/12347',
                        'largeImageURL' => 'https://pixabay.com/large/12347.jpg',
                    ],
                    [
                        'id' => 12348,
                        'user' => 'artist4',
                        'pageURL' => 'https://pixabay.com/photo/12348',
                        'largeImageURL' => 'https://pixabay.com/large/12348.jpg',
                    ],
                    [
                        'id' => 12349,
                        'user' => 'artist5',
                        'pageURL' => 'https://pixabay.com/photo/12349',
                        'largeImageURL' => 'https://pixabay.com/large/12349.jpg',
                    ],
                ],
            ]);

        $this->app->instance(PixabayClient::class, $mockClient);

        $job = new SearchAndPrepareCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        $this->assertEquals('downloading', $session->status);
        $this->assertCount(4, $session->assets);

        $assets = $session->assets()->get();
        $this->assertEquals('candidate', $assets[0]->kind);
        $this->assertEquals('queued', $assets[0]->state);
        $this->assertEquals(12345, $assets[0]->pixabay_id);
        $this->assertEquals('artist1', $assets[0]->pixabay_user);
        $this->assertEquals('https://pixabay.com/large/12345.jpg', $assets[0]->remote_url);

        // Ensure DownloadCandidateImage jobs were dispatched
        Queue::assertPushed(\SzentirasHu\Jobs\DownloadCandidateImage::class, 4);
    }

    /** @test */
    public function it_skips_already_used_pixabay_ids()
    {
        $session = VerseCardSession::factory()->create([
            'keywords' => ['test'],
            'theme_slug' => 'test',
            'status' => 'searching',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        // Create an existing asset with pixabay_id 12345
        VerseCardAsset::factory()->create([
            'session_id' => $session->id,
            'pixabay_id' => 12345,
        ]);

        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andReturn([
                'hits' => [
                    ['id' => 12345, 'user' => 'artist1', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12346, 'user' => 'artist2', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12347, 'user' => 'artist3', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12348, 'user' => 'artist4', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12349, 'user' => 'artist5', 'pageURL' => '...', 'largeImageURL' => '...'],
                ],
            ]);

        $this->app->instance(PixabayClient::class, $mockClient);

        $job = new SearchAndPrepareCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        // Should skip duplicate and create 4 new assets (total 5)
        $this->assertEquals('downloading', $session->status);
        $this->assertCount(5, $session->assets);
        $usedIds = $session->assets()->pluck('pixabay_id')->toArray();
        // Duplicate ID should still be present (existing asset)
        $this->assertContains(12345, $usedIds);
        // New assets should have unique IDs
        $newIds = array_diff($usedIds, [12345]);
        $this->assertCount(4, $newIds);
        $this->assertEqualsCanonicalizing([12346, 12347, 12348, 12349], $newIds);
        // Ensure DownloadCandidateImage jobs were dispatched for the 4 new assets
        Queue::assertPushed(\SzentirasHu\Jobs\DownloadCandidateImage::class, 4);
    }

    /** @test */
    public function it_marks_session_failed_when_no_hits()
    {
        $session = VerseCardSession::factory()->create([
            'status' => 'searching',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andReturn(['hits' => []]);

        $this->app->instance(PixabayClient::class, $mockClient);

        $job = new SearchAndPrepareCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        $this->assertEquals('failed', $session->status);
        $this->assertCount(0, $session->assets);
    }

    /** @test */
    public function it_marks_session_failed_when_not_enough_unique_hits()
    {
        $session = VerseCardSession::factory()->create([
            'status' => 'searching',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andReturn([
                'hits' => [
                    ['id' => 1],
                    ['id' => 2],
                    ['id' => 3],
                ],
            ]);

        $this->app->instance(PixabayClient::class, $mockClient);

        $job = new SearchAndPrepareCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        $this->assertEquals('failed', $session->status);
        $this->assertCount(0, $session->assets);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}