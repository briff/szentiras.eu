<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes anonymous, read-only responses safe to store in a shared (CDN) cache.
 *
 * For routes flagged with the "cacheable" middleware, an anonymous visitor (no
 * anonymous_token cookie) receives a cookie-free "Cache-Control: public"
 * response that Cloudflare can cache. Visitors carrying an anonymous_token get
 * a private, non-stored response so their personalised page is never shared.
 *
 * This middleware must run outside Laravel's session middleware (prepended to
 * the web group) so the session and queued cookies have already been attached
 * to the response by the time it strips them.
 */
class CacheAnonymousResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->attributes->getBoolean('cacheable')) {
            return $response;
        }

        if (! $request->isMethodCacheable() || ! config('page_cache.enabled', true)) {
            return $response;
        }

        $isAnonymous = ! $request->cookie('anonymous_token');

        if ($isAnonymous && $response->getStatusCode() === 200) {
            $response->headers->remove('Set-Cookie');
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }

            $response->setPublic();
            $response->setMaxAge((int) config('page_cache.browser_max_age'));
            $response->setSharedMaxAge((int) config('page_cache.cdn_max_age'));
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
