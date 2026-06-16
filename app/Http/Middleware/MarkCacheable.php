<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Flags a route as eligible for shared (CDN) caching. The actual cookie
 * stripping and Cache-Control headers are applied by CacheAnonymousResponse,
 * which runs outside the session middleware.
 *
 * An optional max-age (seconds) overrides the default page TTLs for both the
 * browser and the CDN — used for short-lived fragments such as commentaries:
 * `->middleware('cacheable:300')`.
 */
class MarkCacheable
{
    public function handle(Request $request, Closure $next, ?string $maxAge = null): Response
    {
        $request->attributes->set('cacheable', true);

        if ($maxAge !== null) {
            $request->attributes->set('cache_max_age', (int) $maxAge);
            $request->attributes->set('cache_cdn_max_age', (int) $maxAge);
        }

        return $next($request);
    }
}
