# Verse Card Controller Implementation Guide

This guide provides example implementations for the backend endpoints required by the VerseCard creator UI.

## Controller Methods

Add these methods to [`app/Http/Controllers/Display/VerseCardController.php`](app/Http/Controllers/Display/VerseCardController.php):

### 1. Create Session (Entry Point)

```php
/**
 * Create a new verse card session and redirect to creator page.
 * Called when user selects a theme from the dialog.
 */
public function createSession(Request $request): RedirectResponse
{
    $request->validate([
        'verse_ref' => 'required|string',
        'verse_text' => 'nullable|string',
        'theme_slug' => 'required|string|exists:themes,slug',
        'keywords' => 'nullable|array',
    ]);

    $session = VerseCardSession::create([
        'user_id' => auth()->id(),
        'verse_ref' => $request->input('verse_ref'),
        'verse_text' => $request->input('verse_text'),
        'theme_slug' => $request->input('theme_slug'),
        'keywords' => $request->input('keywords', []),
        'status' => 'initializing',
        'pixabay_page' => 1,
        'pixabay_offset' => 0,
        'expires_at' => now()->addHours(24),
    ]);

    // Dispatch job to search and prepare initial candidates
    SearchAndPrepareCandidates::dispatch($session);

    return redirect()->route('verse-card.creator', ['sessionId' => $session->id]);
}
```

### 2. Show Creator Page

```php
/**
 * Display the verse card creator page.
 */
public function showCreator(string $sessionId): View
{
    $session = VerseCardSession::findOrFail($sessionId);

    // Verify ownership (optional, for authenticated users)
    if (auth()->check() && $session->user_id !== auth()->id()) {
        abort(403);
    }

    return view('textDisplay.verseCardCreator', [
        'sessionId' => $sessionId,
    ]);
}
```

### 3. Get Session Status

```php
/**
 * Get current session status and candidates if ready.
 * Polled by JavaScript every 2 seconds.
 */
public function getStatus(string $sessionId): JsonResponse
{
    $session = VerseCardSession::findOrFail($sessionId);

    // Check if session has expired
    if ($session->expires_at && $session->expires_at->isPast()) {
        return response()->json([
            'status' => 'error',
            'message' => 'A munkamenet lejárt. Kérjük, kezdje újra.',
        ], 410);
    }

    // Return current status
    if ($session->status === 'final_ready') {
        $finalAsset = $session->assets()
            ->where('kind', 'final')
            ->where('state', 'ready')
            ->latest()
            ->first();

        if ($finalAsset) {
            return response()->json([
                'status' => 'final_ready',
                'final_url' => $this->getAssetUrl($finalAsset),
                'download_url' => route('verse-card.download', ['sessionId' => $session->id]),
            ]);
        }
    }

    if ($session->status === 'candidates_ready') {
        $candidates = $session->assets()
            ->where('kind', 'candidate')
            ->where('state', 'ready')
            ->limit(4)
            ->get()
            ->map(fn($asset) => [
                'id' => $asset->id,
                'thumb_url' => $this->getAssetUrl($asset, 'thumb'),
                'pixabay_id' => $asset->pixabay_id,
                'pixabay_user' => $asset->pixabay_user,
                'pixabay_page_url' => $asset->pixabay_page_url,
            ]);

        if ($candidates->count() >= 4) {
            return response()->json([
                'status' => 'candidates_ready',
                'candidates' => $candidates->values(),
            ]);
        }
    }

    if ($session->status === 'error') {
        return response()->json([
            'status' => 'error',
            'message' => 'Hiba történt a jelöltek keresése közben.',
        ], 422);
    }

    // Still processing
    return response()->json([
        'status' => 'processing',
    ]);
}
```

### 4. Request More Candidates

```php
/**
 * Request more candidate images.
 * Increments pagination and dispatches new search job.
 */
public function requestMore(string $sessionId): JsonResponse
{
    $session = VerseCardSession::findOrFail($sessionId);

    if ($session->status === 'error') {
        return response()->json([
            'status' => 'error',
            'message' => 'Nem lehet több jelöltet keresni hiba után.',
        ], 422);
    }

    // Increment pagination
    $session->update([
        'pixabay_offset' => $session->pixabay_offset + 4,
        'status' => 'searching_more',
    ]);

    // Dispatch job to search more candidates
    SearchAndPrepareCandidates::dispatch($session);

    // Return current status (will be polling)
    return response()->json([
        'status' => 'processing',
    ]);
}
```

### 5. Select Candidate

```php
/**
 * Select a candidate image and start rendering final card.
 */
public function selectCandidate(string $sessionId, Request $request): JsonResponse
{
    $request->validate([
        'candidate_id' => 'required|integer',
    ]);

    $session = VerseCardSession::findOrFail($sessionId);

    $candidateAsset = VerseCardAsset::findOrFail($request->input('candidate_id'));

    if ($candidateAsset->session_id !== $session->id) {
        abort(403);
    }

    // Update session with selected candidate
    $session->update([
        'status' => 'rendering',
    ]);

    // Store selected candidate reference
    $session->assets()
        ->where('kind', 'candidate')
        ->update(['state' => 'unselected']);

    $candidateAsset->update(['state' => 'selected']);

    // Dispatch job to render final card
    RenderVerseCardJob::dispatch($session, $candidateAsset);

    // Return processing status (will be polling)
    return response()->json([
        'status' => 'processing',
    ]);
}
```

### 6. Download Final Image

```php
/**
 * Download the final verse card image.
 * Can be a direct URL or signed download route.
 */
public function download(string $sessionId): StreamResponse|RedirectResponse
{
    $session = VerseCardSession::findOrFail($sessionId);

    $finalAsset = $session->assets()
        ->where('kind', 'final')
        ->where('state', 'ready')
        ->latest()
        ->firstOrFail();

    // Option 1: Stream from storage
    if ($finalAsset->disk === 'local') {
        return response()->download(
            storage_path('app/' . $finalAsset->path),
            'verse-card-' . $session->id . '.png'
        );
    }

    // Option 2: Redirect to S3 signed URL
    if ($finalAsset->disk === 's3') {
        $url = Storage::disk('s3')->temporaryUrl(
            $finalAsset->path,
            now()->addMinutes(30)
        );
        return redirect($url);
    }

    // Option 3: Redirect to remote URL
    if ($finalAsset->remote_url) {
        return redirect($finalAsset->remote_url);
    }

    abort(404);
}
```

### 7. End Session

```php
/**
 * End session and cleanup resources.
 * Called on page unload (best-effort).
 */
public function endSession(string $sessionId): JsonResponse
{
    $session = VerseCardSession::findOrFail($sessionId);

    // Mark as ended
    $session->update(['status' => 'ended']);

    // Optional: Queue cleanup job for temporary files
    // CleanupVerseCardSession::dispatch($session)->delay(now()->addHours(1));

    return response()->json([
        'status' => 'ended',
    ]);
}
```

## Routes

Add these routes to [`routes/web.php`](routes/web.php):

```php
Route::prefix('verse-card')->group(function () {
    // Create session (from theme selection dialog)
    Route::post('/create', '\SzentirasHu\Http\Controllers\Display\VerseCardController@createSession')
        ->name('verse-card.create');

    // Show creator page
    Route::get('/creator/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@showCreator')
        ->name('verse-card.creator');

    // API endpoints (JSON responses)
    Route::get('/status/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@getStatus')
        ->name('verse-card.status');

    Route::post('/more/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@requestMore')
        ->name('verse-card.more');

    Route::post('/select/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@selectCandidate')
        ->name('verse-card.select');

    Route::post('/end/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@endSession')
        ->name('verse-card.end');

    // Download final image
    Route::get('/download/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@download')
        ->name('verse-card.download');
});
```

## Job Classes

### SearchAndPrepareCandidates

```php
namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use SzentirasHu\Data\Entity\VerseCardSession;

class SearchAndPrepareCandidates implements ShouldQueue
{
    public function __construct(private VerseCardSession $session) {}

    public function handle(): void
    {
        try {
            // 1. Search Pixabay for images matching keywords
            $images = $this->searchPixabay(
                $this->session->keywords,
                $this->session->pixabay_page,
                $this->session->pixabay_offset
            );

            if (empty($images)) {
                $this->session->update(['status' => 'error']);
                return;
            }

            // 2. Download and create thumbnails
            foreach ($images as $image) {
                DownloadCandidateImage::dispatch($this->session, $image);
            }

            // 3. Update status (will be marked ready when all downloads complete)
            $this->session->update(['status' => 'candidates_ready']);

        } catch (Exception $e) {
            $this->session->update(['status' => 'error']);
            throw $e;
        }
    }

    private function searchPixabay(array $keywords, int $page, int $offset): array
    {
        // Implementation using Pixabay API
        // Return array of image data
    }
}
```

### RenderVerseCardJob

```php
namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Entity\VerseCardAsset;

class RenderVerseCardJob implements ShouldQueue
{
    public function __construct(
        private VerseCardSession $session,
        private VerseCardAsset $candidateAsset
    ) {}

    public function handle(): void
    {
        try {
            // 1. Load candidate image
            $candidateImage = $this->loadImage($this->candidateAsset);

            // 2. Render verse text overlay
            $finalImage = $this->renderVerseOverlay(
                $candidateImage,
                $this->session->verse_text,
                $this->session->verse_ref
            );

            // 3. Save final image
            $path = $this->saveFinalImage($finalImage);

            // 4. Create asset record
            VerseCardAsset::create([
                'session_id' => $this->session->id,
                'kind' => 'final',
                'state' => 'ready',
                'disk' => 'local',
                'path' => $path,
                'bytes' => filesize(storage_path('app/' . $path)),
                'expires_at' => now()->addDays(7),
            ]);

            // 5. Update session status
            $this->session->update(['status' => 'final_ready']);

        } catch (Exception $e) {
            $this->session->update(['status' => 'error']);
            throw $e;
        }
    }

    private function loadImage(VerseCardAsset $asset): Image
    {
        // Load image using Imagine or similar
    }

    private function renderVerseOverlay(Image $image, string $text, string $reference): Image
    {
        // Render verse text and reference on image
        // Consider text positioning, font size, color, shadow, etc.
    }

    private function saveFinalImage(Image $image): string
    {
        // Save to storage and return path
    }
}
```

## Database Considerations

The `VerseCardSession` and `VerseCardAsset` models should have:

**VerseCardSession:**
- `id` (UUID)
- `user_id` (nullable, for anonymous users)
- `verse_ref` (string)
- `verse_text` (nullable, text)
- `theme_slug` (string)
- `keywords` (JSON array)
- `status` (enum: initializing, searching_more, candidates_ready, rendering, final_ready, error, ended)
- `pixabay_page` (integer)
- `pixabay_offset` (integer)
- `expires_at` (timestamp)
- `created_at`, `updated_at`

**VerseCardAsset:**
- `id` (integer)
- `session_id` (UUID, foreign key)
- `kind` (enum: candidate, final)
- `state` (enum: pending, ready, selected, unselected)
- `pixabay_id` (nullable, integer)
- `pixabay_user` (nullable, string)
- `pixabay_page_url` (nullable, string)
- `remote_url` (nullable, string)
- `disk` (string: local, s3)
- `path` (nullable, string)
- `thumb_path` (nullable, string)
- `bytes` (nullable, integer)
- `expires_at` (nullable, timestamp)
- `created_at`, `updated_at`

## Integration with Existing Dialog

Modify [`resources/views/textDisplay/verseCardDialog.twig`](resources/views/textDisplay/verseCardDialog.twig) to call the create endpoint:

```javascript
// In the theme selection handler
fetch('/verse-card/create', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        verse_ref: selectedVerseRef,
        verse_text: selectedVerseText,
        theme_slug: selectedThemeSlug,
        keywords: extractedKeywords,
    }),
})
.then(response => response.json())
.then(data => {
    if (data.redirect_url) {
        window.location.href = data.redirect_url;
    }
});
```

## Error Handling

The UI handles these error scenarios:
- Network errors (shown with retry button)
- Session expired (410 Gone)
- Invalid session (404 Not Found)
- Processing errors (422 Unprocessable Entity)

Ensure backend returns appropriate HTTP status codes and error messages.

## Performance Tips

1. **Image Optimization**: Compress candidate thumbnails to < 100KB
2. **Async Processing**: Use queues for image downloads and rendering
3. **Caching**: Cache Pixabay search results
4. **Cleanup**: Implement job to delete expired sessions and assets
5. **Database Indexes**: Add indexes on `session_id`, `status`, `expires_at`

## Security Considerations

1. **CSRF Protection**: All POST requests require CSRF token
2. **Authorization**: Verify user ownership of session (if authenticated)
3. **Rate Limiting**: Limit `/more` requests to prevent abuse
4. **Input Validation**: Validate all user inputs
5. **File Storage**: Store files outside public directory
6. **Signed URLs**: Use signed URLs for S3 downloads
7. **Session Expiration**: Automatically cleanup expired sessions
