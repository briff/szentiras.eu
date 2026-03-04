<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Jobs\DownloadCandidateImage;
use SzentirasHu\Jobs\ProvideMoreCandidates;
use SzentirasHu\Services\PixabayClient;
use SzentirasHu\Test\Common\TestCase;

class ProvideMoreCandidatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
    }

    /** @test */
    public function it_acquires_lock_and_skips_if_lock_not_acquired()
    {
        $session = VerseCardSession::factory()->create([
            'expires_at' => Carbon::now()->addHour(),
            'status' => 'choosing',
        ]);

        // Lock already acquired
        $lockKey = 'verse-card:' . $session->id . ':more';
        $lock = Cache::lock($lockKey, 15);
        $lock->get();

        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldNotReceive('search');

        $job = new ProvideMoreCandidates($session->id);
        $job->handle($mockClient);

        // Should not have created any assets
        $this->assertCount(0, $session->assets()->get());
        $lock->release();
    }

    /** @test */
    public function it_handles_expired_session()
    {
        $session = VerseCardSession::factory()->create([
            'expires_at' => Carbon::now()->subHour(),
            'status' => 'choosing',
        ]);

        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldNotReceive('search');

        $job = new ProvideMoreCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        $this->assertEquals('expired', $session->status);
        $this->assertCount(0, $session->assets);
    }

    /** @test */
    public function it_searches_with_page_and_offset_and_creates_candidates()
    {
        $session = VerseCardSession::factory()->create([
            'keywords' => ['peace', 'love'],
            'theme_slug' => 'nature',
            'status' => 'choosing',
            'expires_at' => Carbon::now()->addHour(),
            'pixabay_page' => 1,
            'pixabay_offset' => 4, // already used first 4 hits
        ]);

        // Create some existing assets to mark used IDs
        VerseCardAsset::factory()->create([
            'session_id' => $session->id,
            'pixabay_id' => 12345,
        ]);
        VerseCardAsset::factory()->create([
            'session_id' => $session->id,
            'pixabay_id' => 12346,
        ]);

        // Mock PixabayClient to return hits from page 1 (offset 4)
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
                    // first 4 hits are already used (ids 12345, 12346, ...)
                    ['id' => 12345, 'user' => 'artist1', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12346, 'user' => 'artist2', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12347, 'user' => 'artist3', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12348, 'user' => 'artist4', 'pageURL' => '...', 'largeImageURL' => '...'],
                    // new hits starting at offset 4
                    ['id' => 12349, 'user' => 'artist5', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12350, 'user' => 'artist6', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12351, 'user' => 'artist7', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12352, 'user' => 'artist8', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 12353, 'user' => 'artist9', 'pageURL' => '...', 'largeImageURL' => '...'],
                ],
                'totalHits' => 100,
                'total' => 100,
            ]);

        $job = new ProvideMoreCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        // Should have created 4 new assets (ids 12349-12352)
        $this->assertCount(6, $session->assets); // 2 existing + 4 new
        $newAssets = $session->assets()->whereIn('pixabay_id', [12349, 12350, 12351, 12352])->get();
        $this->assertCount(4, $newAssets);

        // Should have dispatched 4 download jobs
        Queue::assertPushed(DownloadCandidateImage::class, 4);

        // Should have updated page/offset (offset increased by 4, still on page 1)
        $this->assertEquals(1, $session->pixabay_page);
        $this->assertEquals(8, $session->pixabay_offset); // 4 + 4
    }

    /** @test */
    public function it_increments_page_when_offset_exceeds_hits_per_page()
    {
        $session = VerseCardSession::factory()->create([
            'keywords' => ['peace'],
            'theme_slug' => 'nature',
            'status' => 'choosing',
            'expires_at' => Carbon::now()->addHour(),
            'pixabay_page' => 1,
            'pixabay_offset' => 48, // near end of page
        ]);

        // Mock first page with only 2 hits left (total per page 50)
        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->with([
                'q' => 'nature peace',
                'page' => 1,
                'safesearch' => true,
                'image_type' => 'photo',
                'orientation' => 'horizontal',
                'per_page' => 50,
                'order' => 'popular',
            ])
            ->andReturn([
                'hits' => array_fill(0, 50, ['id' => 0, 'user' => '...', 'pageURL' => '...', 'largeImageURL' => '...']),
                'totalHits' => 100,
                'total' => 100,
            ]);
        // Mock second page
        $mockClient->shouldReceive('search')
            ->once()
            ->with([
                'q' => 'nature peace',
                'page' => 2,
                'safesearch' => true,
                'image_type' => 'photo',
                'orientation' => 'horizontal',
                'per_page' => 50,
                'order' => 'popular',
            ])
            ->andReturn([
                'hits' => [
                    ['id' => 200, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 201, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 202, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 203, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                ],
                'totalHits' => 100,
                'total' => 100,
            ]);

        $job = new ProvideMoreCandidates($session->id);
        $job->handle($mockClient);

        $session->refresh();
        // Should have created 4 new assets (1 from page 1, 3 from page 2)
        $this->assertCount(4, $session->assets);
        // Should have updated page to 2 and offset to 3 (since we took 3 hits from page 2)
        $this->assertEquals(2, $session->pixabay_page);
        $this->assertEquals(3, $session->pixabay_offset);
    }

    /** @test */
    public function it_caps_candidate_files_to_last_12()
    {
        $session = VerseCardSession::factory()->create([
            'expires_at' => Carbon::now()->addHour(),
            'status' => 'choosing',
        ]);

        // Create 15 existing candidate assets
        $assets = VerseCardAsset::factory()->count(15)->create([
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'ready',
            'disk' => 'ephemeral',
            'path' => 'some/path.jpg',
        ]);

        // Mock PixabayClient to return hits (we'll create 4 more)
        $mockClient = Mockery::mock(PixabayClient::class);
        $mockClient->shouldReceive('search')
            ->andReturn([
                'hits' => [
                    ['id' => 999, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 998, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 997, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                    ['id' => 996, 'user' => 'artist', 'pageURL' => '...', 'largeImageURL' => '...'],
                ],
                'totalHits' => 100,
                'total' => 100,
            ]);

        $job = new ProvideMoreCandidates($session->id);
        $job->handle($mockClient);

        // After adding 4 new candidates, total would be 19, but cap should delete oldest beyond 12
        $remaining = $session->assets()->where('kind', 'candidate')->count();
        $this->assertLessThanOrEqual(12, $remaining);
        // Should have exactly 12 candidates (since we added 4, deleted 7)
        $this->assertEquals(12, $remaining);
    }
}
