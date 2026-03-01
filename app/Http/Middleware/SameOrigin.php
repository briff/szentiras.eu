<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SameOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow requests from the same origin (browser requests)
        // Reject requests from external origins
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');
        
        // If there's an Origin header, verify it matches the request host
        if ($origin !== null) {
            $requestHost = $request->getHost();
            $originHost = parse_url($origin, PHP_URL_HOST);
            
            if ($originHost !== $requestHost) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }
        
        // If there's a Referer header, verify it matches the request host
        if ($referer !== null) {
            $requestHost = $request->getHost();
            $refererHost = parse_url($referer, PHP_URL_HOST);
            
            if ($refererHost !== $requestHost) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }
        
        // Allow requests without Origin/Referer headers (same-origin browser requests)
        return $next($request);
    }
}
