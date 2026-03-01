<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Key Enforcement
    |--------------------------------------------------------------------------
    |
    | When set to true, all API requests must include a valid X-API-Key header.
    | When false (grace period), requests without a key are allowed but logged.
    |
    */
    'key_required' => env('API_KEY_REQUIRED', false),

    /*
    |--------------------------------------------------------------------------
    | Default Throttle Rate
    |--------------------------------------------------------------------------
    |
    | Default requests per minute for external API keys that have no explicit
    | throttle_rate set. Set to null for unlimited (not recommended).
    |
    */
    'default_throttle' => env('API_KEY_DEFAULT_THROTTLE', 60),

    /*
    |--------------------------------------------------------------------------
    | Internal Key Throttle Rate
    |--------------------------------------------------------------------------
    |
    | Throttle rate for internal keys (is_internal = true). Set to null for
    | unlimited (no throttling). This overrides any per‑key throttle_rate.
    |
    */
    'internal_throttle' => null,

    /*
    |--------------------------------------------------------------------------
    | Whitelisted Domains
    |--------------------------------------------------------------------------
    |
    | Domains from which API requests are allowed without an API key.
    | Requests with an Origin or Referer header matching any of these domains
    | will bypass API key validation.
    |
    */
    'whitelisted_domains' => env('API_WHITELISTED_DOMAINS', 'ujszov.hu'),
];