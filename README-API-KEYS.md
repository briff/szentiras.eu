# API Keys System

## Overview
This system introduces API keys to identify and throttle external API clients, reducing erroneous requests and improving performance. Editors can manage keys via a web interface.

## Features
- **UUID‑like keys** – Random 36‑character tokens, stored as bcrypt hashes.
- **Grace period** – Controlled by `API_KEY_REQUIRED` environment variable.
- **Internal vs external keys** – Internal keys have no throttling; external keys can be rate‑limited.
- **Editor UI** – Create, view, edit, disable, and delete keys under `/editor/api‑keys`.
- **Rate limiting** – Per‑key throttling using Laravel's built‑in rate limiter.
- **Logging** – All API requests (especially errors) are logged for debugging.

## Configuration

### Environment Variables
Add to your `.env` file:

```env
API_KEY_REQUIRED=false          # Set to true to enforce keys (end grace period)
API_KEY_DEFAULT_THROTTLE=60     # Requests per minute for external keys (when null)
API_WHITELISTED_DOMAINS=ujszov.hu # Comma-separated list of domains allowed without API key
```

### Config File
Settings are defined in `config/api.php`.

## Usage

### For API Clients
1. Obtain an API key from an editor.
2. Include the key in the `X‑API‑Key` header of every request.
3. If `API_KEY_REQUIRED=false`, requests without a key are allowed but logged.
4. If `API_KEY_REQUIRED=true`, missing/invalid keys result in `401 Unauthorized`.
5. Requests originating from whitelisted domains (configured via `API_WHITELISTED_DOMAINS`) are allowed without an API key, regardless of the `API_KEY_REQUIRED` setting. The domain is determined by the `Origin` or `Referer` header.

### For Editors
1. Log in as an editor (anonymous token with editor privileges).
2. Navigate to `/editor/api‑keys`.
3. Create a new key, optionally marking it as internal (no throttling) and setting a custom throttle rate.
4. The raw key is displayed **once** after creation – copy it immediately.
5. Manage existing keys: enable/disable, edit details, delete.

## Database Schema

Table `api_keys`:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | varchar(255) | Human‑readable identifier |
| `key_hash` | varchar(255) | Hashed API key (bcrypt) |
| `key_prefix` | char(8) | First 8 chars of raw key for quick lookup |
| `is_internal` | boolean | If true, no throttling applies |
| `throttle_rate` | integer nullable | Requests per minute (null = default) |
| `enabled` | boolean | Whether the key is active |
| `created_by_anonymous_id` | bigint nullable | Foreign key to `anonymous_ids.id` |
| `description` | text nullable | Optional notes |
| `last_used_at` | timestamp nullable | Last successful use |
| `usage_count` | integer default 0 | Total successful uses |
| `created_at`, `updated_at` | timestamp | |

## Middleware

### `VerifyApiKey`
- Registered as `apiKey` middleware.
- Applied to all API routes (see `routes/api.php`).
- Validates the `X‑API‑Key` header, checks hash, updates usage stats, logs requests.
- If the request originates from a whitelisted domain (configured in `api.whitelisted_domains`), the API key validation is skipped and the request is allowed.

### Rate Limiting
- Defined in `AppServiceProvider::boot()` as `api_key` rate limiter.
- Applied via `throttle:api_key` middleware after key verification.
- Internal keys have unlimited requests; external keys respect `throttle_rate` or default.

## Routes

### API Routes (`routes/api.php`)
All API endpoints are wrapped in:
```php
Route::middleware(['apiKey', 'throttle:api_key'])->group(...)
```

### Editor Routes (`routes/web.php`)
Under `editor/` prefix, protected by `editor` middleware:
- `GET  /editor/api‑keys` – list keys
- `GET  /editor/api‑keys/create` – create form
- `POST /editor/api‑keys` – store new key
- `GET  /editor/api‑keys/{id}` – show key (with raw key on creation)
- `GET  /editor/api‑keys/{id}/edit` – edit form
- `PUT  /editor/api‑keys/{id}` – update key
- `DELETE /editor/api‑keys/{id}` – delete key

## Logging
- Missing keys during grace period are logged as `info`.
- Invalid/disabled keys are logged as `warning`.
- Successful requests are logged as `info` with key ID, IP, path, and internal flag.
- Use `storage/logs/laravel.log` or your configured logging channel.

## Testing
Run the test suite:
```bash
php artisan test --filter ApiKeyTest
```

## Deployment Steps
1. Run the migration:
   ```bash
   php artisan migrate
   ```
2. Set `API_KEY_REQUIRED=false` in production (grace period).
3. Notify API users about the upcoming change.
4. Create internal keys for your own services.
5. After the grace period, set `API_KEY_REQUIRED=true` to enforce keys.
6. Monitor logs for errors and throttling events.

## Troubleshooting

### "Unable to locate file in Vite manifest"
Run `npm run build` or `composer run dev`.

### Middleware not applied
Check that `routes/api.php` uses the `apiKey` middleware group.

### Rate limiting not working
Verify that the `api_key` rate limiter is defined in `AppServiceProvider` and that the `throttle:api_key` middleware is applied after `apiKey`.

### Editor UI not accessible
Ensure the user has an editor token listed in `EDITOR_TOKENS` environment variable.

## Future Enhancements
- API key rotation (optional).
- Usage statistics dashboard.
- Webhook for key expiration notifications.
- Automated grace‑period ending based on date.

## Support
For questions, contact the development team or refer to the implementation plan in `plans/api-keys-implementation.md`.
