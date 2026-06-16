<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Page Caching
    |--------------------------------------------------------------------------
    |
    | Controls the shared (CDN) caching of anonymous, read-only text pages.
    | When enabled, anonymous GET responses on routes flagged with the
    | "cacheable" middleware are stripped of cookies and served with a
    | "Cache-Control: public" header so Cloudflare can cache them. Since Bible
    | texts change rarely, the CDN copy is invalidated explicitly via the
    | "cdn:purge" command rather than relying on a short TTL.
    |
    */

    'enabled' => env('PUBLIC_PAGE_CACHE_ENABLED', true),

    'browser_max_age' => (int) env('PUBLIC_PAGE_BROWSER_MAX_AGE', 300),

    'cdn_max_age' => (int) env('PUBLIC_PAGE_CDN_MAX_AGE', 2592000),

];
