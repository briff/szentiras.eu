<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Data\Entity\ApiKey;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if request originates from a whitelisted domain
        if ($this->isFromWhitelistedDomain($request)) {
            // Allow request without API key
            Log::info('API request from whitelisted domain', [
                'domain' => $this->getRequestDomain($request),
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            return $next($request);
        }

        $apiKey = $request->header('X-API-Key');

        // If no key provided
        if (!$apiKey) {
            // Grace period: if API_KEY_REQUIRED is false, allow but log
            if (!config('api.key_required', false)) {
                Log::info('API request without key (grace period)', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);
                return $next($request);
            }

            // Enforcement period: reject
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide a valid X-API-Key header.',
            ], 401);
        }

        // Look up key by prefix (first 8 characters)
        $prefix = substr($apiKey, 0, 8);
        $apiKeyModel = ApiKey::where('key_prefix', $prefix)
            ->where('enabled', true)
            ->first();

        // No matching key
        if (!$apiKeyModel) {
            Log::warning('Invalid API key prefix', [
                'prefix' => $prefix,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid.',
            ], 401);
        }

        // Verify hash
        if (!Hash::check($apiKey, $apiKeyModel->key_hash)) {
            Log::warning('API key hash mismatch', [
                'api_key_id' => $apiKeyModel->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid.',
            ], 401);
        }

        // Key is disabled
        if (!$apiKeyModel->enabled) {
            Log::warning('Disabled API key used', [
                'api_key_id' => $apiKeyModel->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json([
                'error' => 'API key disabled',
                'message' => 'This API key has been disabled.',
            ], 403);
        }

        // Update usage stats
        $apiKeyModel->update([
            'last_used_at' => now(),
            'usage_count' => $apiKeyModel->usage_count + 1,
        ]);

        // Attach key to request for downstream use (e.g., logging, throttling)
        $request->attributes->set('apiKey', $apiKeyModel);

        // Log successful API request
        Log::info('API request with key', [
            'api_key_id' => $apiKeyModel->id,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'is_internal' => $apiKeyModel->is_internal,
        ]);

        // If external key, apply rate limiting via separate middleware
        // (Rate limiting will be handled by Laravel's throttle middleware)
        // We'll just pass the request along.

        return $next($request);
    }

    /**
     * Determine if the request originates from a whitelisted domain.
     */
    private function isFromWhitelistedDomain(Request $request): bool
    {
        $domain = $this->getRequestDomain($request);
        if (!$domain) {
            return false;
        }

        $whitelisted = config('api.whitelisted_domains', '');
        if (is_string($whitelisted)) {
            $whitelisted = array_map('trim', explode(',', $whitelisted));
        }

        return in_array($domain, $whitelisted, true);
    }

    /**
     * Extract the domain from Origin or Referer header.
     */
    private function getRequestDomain(Request $request): ?string
    {
        $origin = $request->header('Origin');
        if ($origin) {
            $host = parse_url($origin, PHP_URL_HOST);
            if ($host) {
                return $host;
            }
        }

        $referer = $request->header('Referer');
        if ($referer) {
            $host = parse_url($referer, PHP_URL_HOST);
            if ($host) {
                return $host;
            }
        }

        return null;
    }
}