<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use SzentirasHu\Jobs\FetchDailyReadingJob;
use SzentirasHu\Jobs\QueueDailyReadingCommentariesJob;
use SzentirasHu\Models\DailyReading;
use SzentirasHu\Service\DailyReadingService;
use SzentirasHu\Test\Common\TestCase;

class FetchDailyReadingTest extends TestCase
{
    use RefreshDatabase;

    private array $sampleResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sampleResponse = [
            'date' => [
                'ISO' => '2026-03-07',
                'dayOfYear' => '66',
            ],
            'celebration' => [
                [
                    'celebrationKey' => 0,
                    'name' => 'nagyböjti idő 2. hét, szombat',
                    'parts' => [
                        ['short_title' => 'olvasmány', 'ref' => 'Mik 7,14-15.18-20'],
                        ['short_title' => 'zsoltár', 'ref' => 'Zsolt 102,1-2.3-4'],
                        ['short_title' => 'evangélium előtti vers', 'ref' => null],
                        ['short_title' => 'evangélium', 'ref' => 'Lk 15,1-3.11-32'],
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // DailyReadingService tests
    // -------------------------------------------------------------------------

    public function test_service_fetches_and_stores_successful_response(): void
    {
        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response($this->sampleResponse, 200),
        ]);

        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);
        $date = new \DateTimeImmutable('2026-03-07');
        $result = $service->fetchAndStore($date);

        $this->assertNotNull($result);
        $this->assertEquals(DailyReading::STATUS_FETCHED, $result->status);
        $this->assertEquals('nagyböjti idő 2. hét, szombat', $result->celebration_name);
        $this->assertNotEmpty($result->processed_refs);
    }

    public function test_service_stores_failed_status_on_http_error(): void
    {
        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response(null, 503),
        ]);

        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);
        $result = $service->fetchAndStore(new \DateTimeImmutable('2026-03-07'));

        $this->assertNull($result);
        $this->assertDatabaseHas('daily_readings', [
            'date' => '2026-03-07',
            'status' => DailyReading::STATUS_FAILED,
        ]);
    }

    public function test_service_stores_failed_status_on_connection_error(): void
    {
        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response(null, 404),
        ]);

        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);
        $result = $service->fetchAndStore(new \DateTimeImmutable('2026-03-07'));

        $this->assertNull($result);
        $this->assertDatabaseHas('daily_readings', [
            'status' => DailyReading::STATUS_FAILED,
        ]);
    }

    public function test_service_updates_existing_record_on_refetch(): void
    {
        DailyReading::factory()->create([
            'date' => '2026-03-07',
            'status' => DailyReading::STATUS_FAILED,
        ]);

        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response($this->sampleResponse, 200),
        ]);

        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);
        $service->fetchAndStore(new \DateTimeImmutable('2026-03-07'));

        $this->assertDatabaseCount('daily_readings', 1);
        $this->assertDatabaseHas('daily_readings', [
            'date' => '2026-03-07',
            'status' => DailyReading::STATUS_FETCHED,
        ]);
    }

    // -------------------------------------------------------------------------
    // cleanRef tests
    // -------------------------------------------------------------------------

    public function test_clean_ref_removes_alternatives_with_colon(): void
    {
        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);

        $this->assertEquals('Mik 7,14-15', $service->cleanRef('Mik 7,14-15 vagy: Mik 7,18'));
    }

    public function test_clean_ref_removes_alternatives_without_colon(): void
    {
        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);

        $this->assertEquals('Lk 15,1-3', $service->cleanRef('Lk 15,1-3 vagy Lk 15,11-32'));
    }

    public function test_clean_ref_replaces_es_with_dot(): void
    {
        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);

        $this->assertEquals('Zsolt 102,1-2.3-4', $service->cleanRef('Zsolt 102,1-2 és 3-4'));
    }

    // -------------------------------------------------------------------------
    // Processed refs content
    // -------------------------------------------------------------------------

    public function test_service_skips_parts_without_ref(): void
    {
        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response($this->sampleResponse, 200),
        ]);

        /** @var DailyReadingService $service */
        $service = app(DailyReadingService::class);
        $result = $service->fetchAndStore(new \DateTimeImmutable('2026-03-07'));

        // 'evangélium előtti vers' has null ref and must not appear
        $this->assertNotNull($result);
        foreach ($result->processed_refs as $ref) {
            $this->assertNotEmpty($ref);
        }
    }

    // -------------------------------------------------------------------------
    // DailyReading model
    // -------------------------------------------------------------------------

    public function test_is_available_returns_true_for_fetched_status(): void
    {
        $reading = DailyReading::factory()->available()->make();

        $this->assertTrue($reading->isAvailable());
    }

    public function test_is_available_returns_true_for_commentaries_queued_status(): void
    {
        $reading = DailyReading::factory()->commentariesQueued()->make();

        $this->assertTrue($reading->isAvailable());
    }

    public function test_is_available_returns_false_for_failed_status(): void
    {
        $reading = DailyReading::factory()->failed()->make();

        $this->assertFalse($reading->isAvailable());
    }

    public function test_is_available_returns_false_when_no_processed_refs(): void
    {
        $reading = DailyReading::factory()->make([
            'status' => DailyReading::STATUS_FETCHED,
            'processed_refs' => null,
        ]);

        $this->assertFalse($reading->isAvailable());
    }

    public function test_get_combined_ref_string_joins_refs_with_semicolon(): void
    {
        $reading = DailyReading::factory()->make([
            'processed_refs' => ['Mik7,14-15', 'Lk15,1-3'],
        ]);

        $this->assertEquals('Mik7,14-15;Lk15,1-3', $reading->combinedRefString);
    }

    public function test_get_combined_ref_string_returns_empty_when_no_refs(): void
    {
        $reading = DailyReading::factory()->make(['processed_refs' => null]);

        $this->assertEquals('', $reading->combinedRefString);
    }

    // -------------------------------------------------------------------------
    // FetchDailyReadingJob
    // -------------------------------------------------------------------------

    public function test_fetch_job_dispatches_commentary_job_on_success(): void
    {
        Queue::fake();

        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response($this->sampleResponse, 200),
        ]);

        $job = new FetchDailyReadingJob('2026-03-07');
        $job->handle(app(DailyReadingService::class));

        Queue::assertPushed(QueueDailyReadingCommentariesJob::class);
    }

    public function test_fetch_job_does_not_dispatch_commentary_job_on_failure(): void
    {
        Queue::fake();

        Http::fake([
            'szentjozsefhackathon.github.io/*' => Http::response(null, 503),
        ]);

        $job = new FetchDailyReadingJob('2026-03-07');
        $job->handle(app(DailyReadingService::class));

        Queue::assertNotPushed(QueueDailyReadingCommentariesJob::class);
    }
}

