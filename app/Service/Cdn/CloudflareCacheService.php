<?php

namespace SzentirasHu\Service\Cdn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareCacheService
{
    public function __construct(
        private ?string $zoneId,
        private ?string $apiToken,
        private string $apiBaseUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->zoneId) && ! empty($this->apiToken);
    }

    /**
     * Purge the entire Cloudflare cache for the configured zone.
     *
     * Texts change rarely, so a full purge is acceptable and works on every
     * Cloudflare plan (cache-tag purging requires Enterprise).
     */
    public function purgeEverything(): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Cloudflare cache purge skipped: zone id or API token not configured.');

            return false;
        }

        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->post("{$this->apiBaseUrl}/zones/{$this->zoneId}/purge_cache", [
                'purge_everything' => true,
            ]);

        if ($response->successful() && $response->json('success') === true) {
            return true;
        }

        Log::error('Cloudflare cache purge failed.', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }
}
