<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Service\Sitemap\SitemapGenerator;

class GenerateSitemap extends Command
{
    protected $signature = 'szentiras:generate-sitemap
                            {--path= : Absolute path to write the sitemap to (defaults to public/sitemap.xml)}';

    protected $description = 'Build the sitemap and write it to a static file so the web server can serve it directly.';

    public function handle(SitemapGenerator $sitemapGenerator): int
    {
        $path = $this->option('path') ?: public_path('sitemap.xml');

        $this->info('Building sitemap...');
        $xml = $sitemapGenerator->generate();

        if (file_put_contents($path, $xml) === false) {
            $this->error("Failed to write sitemap to {$path}.");

            return self::FAILURE;
        }

        Cache::forget('sitemap.xml');

        $urlCount = substr_count($xml, '<url>');
        $this->info("Sitemap written to {$path} ({$urlCount} URLs).");

        return self::SUCCESS;
    }
}
