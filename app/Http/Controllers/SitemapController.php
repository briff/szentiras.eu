<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Service\Sitemap\SitemapGenerator;

class SitemapController extends Controller
{
    public function __construct(
        protected SitemapGenerator $sitemapGenerator
    ) {
    }

    public function index(): Response
    {
        $staticPath = public_path('sitemap.xml');

        if (is_file($staticPath)) {
            return response((string) file_get_contents($staticPath), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }

        $xml = Cache::remember('sitemap.xml', now()->addDay(), function (): string {
            return $this->sitemapGenerator->generate();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
