<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Service\Cdn\CloudflareCacheService;

class PurgeCdnCache extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cdn:purge';

    /**
     * @var string
     */
    protected $description = 'Purge the Cloudflare CDN cache (run after updating Bible texts).';

    public function handle(CloudflareCacheService $cloudflare): int
    {
        if (! $cloudflare->isConfigured()) {
            $this->warn('Cloudflare is not configured (CLOUDFLARE_ZONE_ID / CLOUDFLARE_API_TOKEN). Skipping purge.');

            return self::SUCCESS;
        }

        if ($cloudflare->purgeEverything()) {
            $this->info('Cloudflare cache purged successfully.');

            return self::SUCCESS;
        }

        $this->error('Cloudflare cache purge failed. Check the logs.');

        return self::FAILURE;
    }
}
