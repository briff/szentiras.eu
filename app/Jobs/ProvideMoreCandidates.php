<?php

namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Services\PixabayClient;

class ProvideMoreCandidates extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The session ID.
     *
     * @var string
     */
    protected string $sessionId;

    /**
     * Create a new job instance.
     *
     * @param string $sessionId
     */
    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job.
     *
     * @param PixabayClient $pixabayClient
     * @return void
     */
    public function handle(PixabayClient $pixabayClient): void
    {
        Log::info('ProvideMoreCandidates started', ['sessionId' => $this->sessionId]);

        // Acquire Redis lock per session (10-15 seconds)
        $lockKey = 'verse-card:' . $this->sessionId . ':more';
        $lock = Cache::lock($lockKey, 15); // 15 seconds
        if (! $lock->get()) {
            Log::warning('Could not acquire lock for session', ['sessionId' => $this->sessionId]);
            return;
        }

        try {
            // Load session; abort if expired
            $session = VerseCardSession::find($this->sessionId);
            if (! $session) {
                Log::error('Session not found', ['sessionId' => $this->sessionId]);
                return;
            }

            if ($session->expires_at && $session->expires_at->isPast()) {
                Log::warning('Session expired', ['sessionId' => $this->sessionId]);
                $session->status = VerseCardSessionStatus::Expired->value;
                $session->save();
                return;
            }

            // Build search params (same as SearchAndPrepareCandidates)
            $params = $this->buildSearchParams($session);

            // Determine current page & offset
            $page = (int) ($session->pixabay_page ?? 1);
            $offset = (int) ($session->pixabay_offset ?? 0);

            // Get already used Pixabay IDs for this session
            $usedPixabayIds = $this->getUsedPixabayIds($session);

            $selectedHits = [];
            $totalPages = null;

            // Loop until we have 4 hits or run out of pages
            while (count($selectedHits) < 4) {
                // Fetch hits for this page (cached)
                $params['page'] = $page;
                try {
                    $response = $pixabayClient->search($params);
                } catch (\Throwable $e) {
                    Log::error('Pixabay search failed', [
                        'sessionId' => $this->sessionId,
                        'page' => $page,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }

                $hits = $response['hits'] ?? [];
                if (empty($hits)) {
                    Log::warning('No hits returned from Pixabay', [
                        'sessionId' => $this->sessionId,
                        'page' => $page,
                    ]);
                    break;
                }

                // Store total pages for sanity check
                if ($totalPages === null) {
                    $totalHits = $response['totalHits'] ?? $response['total'] ?? 0;
                    $perPage = $params['per_page'] ?? 50;
                    $totalPages = max(1, ceil($totalHits / $perPage));
                }

                // Iterate through hits starting at offset (only for the first page we're processing)
                $start = ($page === $session->pixabay_page) ? $offset : 0;
                for ($i = $start; $i < count($hits); $i++) {
                    $hit = $hits[$i];
                    if (! isset($hit['id'])) {
                        continue;
                    }
                    if (in_array($hit['id'], $usedPixabayIds)) {
                        // Skip already used, but still count as consumed (offset will be incremented)
                        continue;
                    }
                    $selectedHits[] = $hit;
                    $usedPixabayIds[] = $hit['id'];

                    if (count($selectedHits) >= 4) {
                        // Update offset to the next position after this hit
                        $offset = $i + 1;
                        break 2; // break out of both loops
                    }
                }

                // If we exhausted this page, move to next page
                $page++;
                $offset = 0;

                // Stop if we've gone beyond total pages
                if ($page > $totalPages) {
                    Log::warning('Reached last page, not enough unique hits', [
                        'sessionId' => $this->sessionId,
                        'totalPages' => $totalPages,
                    ]);
                    break;
                }
            }

            if (count($selectedHits) === 0) {
                Log::warning('No new candidate hits available', ['sessionId' => $this->sessionId]);
                // No candidates added, but we keep session unchanged
                return;
            }

            // Insert candidate assets
            $assetIds = [];
            foreach ($selectedHits as $hit) {
                $asset = new VerseCardAsset([
                    'session_id' => $session->id,
                    'kind' => 'candidate',
                    'state' => 'queued',
                    'pixabay_id' => $hit['id'],
                    'pixabay_user' => $hit['user'] ?? null,
                    'pixabay_page_url' => $hit['pageURL'] ?? null,
                    'remote_url' => $hit['largeImageURL'] ?? null,
                    'disk' => 'ephemeral',
                    'expires_at' => $session->expires_at,
                ]);
                $asset->save();
                $assetIds[] = $asset->id;
            }

            // Update session cursor
            $session->pixabay_page = $page;
            $session->pixabay_offset = $offset;
            $session->save();

            // Dispatch download jobs
            foreach ($assetIds as $assetId) {
                DownloadCandidateImage::dispatch($assetId)->onQueue('image-download');
            }

            // Cap candidate files: delete older candidate files beyond last 12
            $this->capCandidateFiles($session);

            Log::info('ProvideMoreCandidates completed', [
                'sessionId' => $this->sessionId,
                'assets_created' => count($assetIds),
                'new_page' => $page,
                'new_offset' => $offset,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Build search parameters from session keywords and theme.
     *
     * @param VerseCardSession $session
     * @return array
     */
    private function buildSearchParams(VerseCardSession $session): array
    {
        $keywords = $session->keywords ?? [];
        $themeSlug = $session->theme_slug;

        $queryParts = [$themeSlug];
        foreach ($keywords as $keyword) {
            $queryParts[] = $keyword;
        }
        $query = implode(' ', $queryParts);

        return [
            'q' => $query,
            'safesearch' => true,
            'image_type' => 'photo',
            'orientation' => 'horizontal',
            'per_page' => 50,
            'order' => 'popular',
            // page will be added later
        ];
    }

    /**
     * Get Pixabay IDs already used in this session.
     *
     * @param VerseCardSession $session
     * @return array
     */
    private function getUsedPixabayIds(VerseCardSession $session): array
    {
        return $session->assets()
            ->whereNotNull('pixabay_id')
            ->pluck('pixabay_id')
            ->toArray();
    }

    /**
     * Delete older candidate files beyond the last 12.
     *
     * @param VerseCardSession $session
     * @return void
     */
    private function capCandidateFiles(VerseCardSession $session): void
    {
        $candidates = $session->assets()
            ->where('kind', 'candidate')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($candidates->count() <= 12) {
            return;
        }

        $toDelete = $candidates->slice(12);
        /** @var \SzentirasHu\Data\Entity\VerseCardAsset $asset */
        foreach ($toDelete as $asset) {
            // Delete file from storage if path exists
            if ($asset->disk && $asset->path) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
            // Delete thumb file if exists
            if ($asset->disk && $asset->thumb_path) {
                Storage::disk($asset->disk)->delete($asset->thumb_path);
            }
            // Delete the asset record
            $asset->delete();
        }

        Log::info('Capped candidate files', [
            'sessionId' => $session->id,
            'deleted' => $toDelete->count(),
        ]);
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProvideMoreCandidates job failed', [
            'sessionId' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        $session = VerseCardSession::find($this->sessionId);
        if ($session) {
            $session->status = VerseCardSessionStatus::Failed->value;
            $session->save();
        }
    }
}
