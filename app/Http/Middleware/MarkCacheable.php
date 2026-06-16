<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Flags a route as eligible for shared (CDN) caching. The actual cookie
 * stripping and Cache-Control headers are applied by CacheAnonymousResponse,
 * which runs outside the session middleware.
 */
class MarkCacheable
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('cacheable', true);

        return $next($request);
    }
}
