<?php

namespace SzentirasHu\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Data\Entity\PixabaySearchCache;

class PixabayClient
{
    public const DEFAULT_PARAMS = [
        'safesearch' => true,
        'image_type' => 'photo',
        'per_page' => 24,
        'category' => 'nature,religion,people,places,travel,buildings,music,backgrounds',
        'order' => 'popular',
    ];

    private readonly string $apiKey;

    private readonly string $baseUrl;

    public function __construct(
        ConfigRepository $config,
    ) {
        $pixabayConfig = $config->get('services.pixabay');
        if (! isset($pixabayConfig['key']) || ! isset($pixabayConfig['base_url'])) {
            throw new \RuntimeException('Pixabay configuration missing.');
        }
        $this->apiKey = $pixabayConfig['key'];
        $this->baseUrl = $pixabayConfig['base_url'];
    }

    /**
     * Search Pixabay images with caching.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function search(array $params): array
    {
        $canonicalParams = $this->buildCanonicalParams($params);
        $cacheKey = $this->generateCacheKey($canonicalParams);

        // Try to retrieve from cache
        $cached = PixabaySearchCache::where('cache_key', $cacheKey)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($cached) {
            return $cached->response;
        }

        // Call API
        $response = $this->callApi($canonicalParams);

        // Store in cache
        PixabaySearchCache::updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'params' => $canonicalParams,
                'response' => $response,
                'expires_at' => Carbon::now()->addHours(24),
            ]
        );

        return $response;
    }

    /**
     * Build canonical parameter list with defaults.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildCanonicalParams(array $params): array
    {
        $merged = array_merge(self::DEFAULT_PARAMS, $params);

        // Ensure required 'q' parameter is present
        if (! isset($merged['q']) || empty($merged['q'])) {
            throw new \InvalidArgumentException('Missing required search query (q)');
        }

        // Normalize boolean values to true/false strings? Pixabay expects 'true'/'false'?
        // According to Pixabay docs, safesearch expects 'true' string.
        $merged['safesearch'] = $this->normalizeBoolean($merged['safesearch']);

        // Sort keys for consistency
        ksort($merged);

        return $merged;
    }

    /**
     * Generate cache key from canonical parameters.
     */
    private function generateCacheKey(array $canonicalParams): string
    {
        return sha1(json_encode($canonicalParams));
    }

    /**
     * Call Pixabay REST API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callApi(array $params): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout(30)
                ->retry(3, 100)
                ->get('', array_merge(['key' => $this->apiKey], $params));
            $response->throw();
        } catch (RequestException $e) {
            $response = $e->response;
            if ($response) {
                $this->handleApiError($response);
            }
            // No response indicates a connection error (retryable)
            throw new \RuntimeException('Pixabay API connection error: '.$e->getMessage(), 0, $e);
        }

        return $response->json();
    }

    /**
     * Handle API errors, distinguishing retryable from fatal.
     */
    private function handleApiError(\Illuminate\Http\Client\Response $response): void
    {
        $status = $response->status();
        $body = $response->body();

        Log::error('Pixabay API error', [
            'status' => $status,
            'body' => $body,
        ]);

        // Determine if error is retryable
        $retryable = $status >= 500 || $status === 429;

        if ($retryable) {
            throw new \RuntimeException("Pixabay API temporary error: {$status}", $status);
        }

        // Fatal errors (e.g., 400, 401, 403, 404)
        throw new \RuntimeException("Pixabay API fatal error: {$status}", $status);
    }

    /**
     * Normalize boolean to string 'true' or 'false' as Pixabay expects.
     */
    private function normalizeBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value);
        }

        // Default to 'true' for truthy values? We'll assume true.
        return (bool) $value ? 'true' : 'false';
    }
}
