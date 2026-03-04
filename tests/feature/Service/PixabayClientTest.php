<?php

namespace SzentirasHu\Test\Service;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Http;
use Mockery;
use SzentirasHu\Data\Entity\PixabaySearchCache;
use SzentirasHu\Services\PixabayClient;
use SzentirasHu\Test\Common\TestCase;

class PixabayClientTest extends TestCase
{
    private PixabayClient $client;

    private Mockery\MockInterface $configMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configMock = Mockery::mock(ConfigRepository::class);
        $this->configMock->shouldReceive('get')
            ->with('services.pixabay')
            ->andReturn([
                'key' => 'test-api-key',
                'base_url' => 'https://pixabay.com/api/',
            ]);

        $this->client = new PixabayClient($this->configMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_returns_cached_response(): void
    {
        $params = ['q' => 'jesus', 'category' => 'religion'];
        $canonicalParams = [
            'safesearch' => 'true',
            'image_type' => 'photo',
            'orientation' => 'horizontal',
            'per_page' => 50,
            'order' => 'popular',
            'q' => 'jesus',
            'category' => 'religion',
        ];
        ksort($canonicalParams);
        $cacheKey = sha1(json_encode($canonicalParams));

        $cachedResponse = ['total' => 10, 'hits' => []];

        // Mock PixabaySearchCache model
        $mockCache = Mockery::mock('overload:'.PixabaySearchCache::class);
        $mockQuery = Mockery::mock();
        $mockCache->shouldReceive('where')
            ->with('cache_key', Mockery::any())
            ->once()
            ->andReturn($mockQuery);
        $mockQuery->shouldReceive('where')
            ->with('expires_at', '>', Mockery::any())
            ->once()
            ->andReturn($mockQuery);
        $mockQuery->shouldReceive('first')
            ->once()
            ->andReturn((object) ['response' => $cachedResponse]);

        $result = $this->client->search($params);

        $this->assertEquals($cachedResponse, $result);
    }

    public function test_search_calls_api_when_cache_missing(): void
    {
        $params = ['q' => 'jesus'];
        $canonicalParams = [
            'safesearch' => 'true',
            'image_type' => 'photo',
            'orientation' => 'horizontal',
            'per_page' => 50,
            'order' => 'popular',
            'q' => 'jesus',
        ];
        ksort($canonicalParams);
        $cacheKey = sha1(json_encode($canonicalParams));

        // Mock cache miss
        $mockCache = Mockery::mock('overload:'.PixabaySearchCache::class);
        $mockQuery = Mockery::mock();
        $mockCache->shouldReceive('where')
            ->with('cache_key', Mockery::any())
            ->once()
            ->andReturn($mockQuery);
        $mockQuery->shouldReceive('where')
            ->with('expires_at', '>', Mockery::any())
            ->once()
            ->andReturn($mockQuery);
        $mockQuery->shouldReceive('first')
            ->once()
            ->andReturnNull();

        // Mock updateOrCreate
        $mockCache->shouldReceive('updateOrCreate')
            ->with(Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(new PixabaySearchCache);

        // Fake HTTP response
        Http::fake([
            'pixabay.com/api/*' => Http::response([
                'total' => 5,
                'hits' => [
                    ['id' => 123, 'previewURL' => '...'],
                ],
            ]),
        ]);

        $result = $this->client->search($params);

        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(5, $result['total']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'pixabay.com/api/') &&
                   $request->method() === 'GET' &&
                   $request->data()['key'] === 'test-api-key' &&
                   $request->data()['q'] === 'jesus';
        });
    }

    public function test_search_throws_exception_on_missing_query(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required search query (q)');

        $this->client->search(['category' => 'religion']);
    }

    public function test_search_handles_api_error_retryable(): void
    {
        $params = ['q' => 'jesus'];

        // Mock cache miss
        $mockCache = Mockery::mock('overload:'.PixabaySearchCache::class);
        $mockQuery = Mockery::mock();
        $mockCache->shouldReceive('where')->andReturn($mockQuery);
        $mockQuery->shouldReceive('where')->andReturn($mockQuery);
        $mockQuery->shouldReceive('first')->andReturnNull();

        Http::fake([
            'pixabay.com/api/*' => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pixabay API temporary error: 503');

        $this->client->search($params);
    }

    public function test_search_handles_api_error_fatal(): void
    {
        $params = ['q' => 'jesus'];

        // Mock cache miss
        $mockCache = Mockery::mock('overload:'.PixabaySearchCache::class);
        $mockQuery = Mockery::mock();
        $mockCache->shouldReceive('where')->andReturn($mockQuery);
        $mockQuery->shouldReceive('where')->andReturn($mockQuery);
        $mockQuery->shouldReceive('first')->andReturnNull();

        Http::fake([
            'pixabay.com/api/*' => Http::response('Bad Request', 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pixabay API fatal error: 400');

        $this->client->search($params);
    }
}
