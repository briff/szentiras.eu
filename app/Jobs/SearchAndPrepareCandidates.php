<?php

namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Services\PixabayClient;
use SzentirasHu\Jobs\DownloadCandidateImage;

class SearchAndPrepareCandidates extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The session ID.
     *
     * @var string
     */
    protected string $sessionId;

    /**
     * The page number to search.
     *
     * @var int
     */
    protected int $page;

    /**
     * Create a new job instance.
     *
     * @param string $sessionId
     * @param int $page
     */
    public function __construct(string $sessionId, int $page = 1)
    {
        $this->sessionId = $sessionId;
        $this->page = $page;
    }

    /**
     * Execute the job.
     *
     * @param PixabayClient $pixabayClient
     * @return void
     */
    public function handle(PixabayClient $pixabayClient): void
    {
        Log::info('SearchAndPrepareCandidates started', ['sessionId' => $this->sessionId]);

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

        // Get used Pixabay IDs early
        $usedPixabayIds = $this->getUsedPixabayIds($session);

        // Fetch candidates, potentially across multiple pages
        $candidateHits = $this->fetchCandidates($pixabayClient, $session, $usedPixabayIds, 4);

        if (count($candidateHits) < 4) {
            Log::warning('Not enough unique hits available', [
                'sessionId' => $this->sessionId,
                'available' => count($candidateHits),
            ]);
            $session->status = VerseCardSessionStatus::Failed->value;
            $session->save();
            return;
        }

        // Insert 4 assets: kind=candidate, state=queued
        $assetIds = [];
        foreach ($candidateHits as $hit) {
            $asset = new VerseCardAsset([
                'session_id' => $session->id,
                'kind' => 'candidate',
                'state' => 'queued',
                'pixabay_id' => $hit['id'],
                'pixabay_user' => $hit['user'] ?? null,
                'pixabay_page_url' => $hit['pageURL'] ?? null,
                'remote_url' => $hit['largeImageURL'] ?? null,
                'web_format_url' => $hit['webFormatURL'] ?? null,
                'width' => $hit['imageWidth'] ?? null,
                'height' => $hit['imageHeight'] ?? null,
                'disk' => 'ephemeral',
                'expires_at' => $session->expires_at,
            ]);
            $asset->save();
            $assetIds[] = $asset->id;
        }

        // Download web format images and create thumbnails
        foreach ($assetIds as $assetId) {
            $this->downloadWebFormatImage($assetId);
        }

        // Dispatch DownloadCandidateImage jobs for resize transformations
        foreach ($assetIds as $assetId) {
            DownloadCandidateImage::dispatch($assetId)->onQueue('image-download');
        }

        // Set session status to downloading — UI can now show metadata/placeholders
        $session->status = VerseCardSessionStatus::Downloading->value;
        $session->save();

        Log::info('SearchAndPrepareCandidates completed', [
            'sessionId' => $this->sessionId,
            'assets_created' => count($assetIds),
        ]);
    }

    /**
     * Fetch candidate hits, fetching additional pages if needed.
     *
     * @param PixabayClient $pixabayClient
     * @param VerseCardSession $session
     * @param array $usedPixabayIds
     * @param int $limit
     * @return array
     */
    private function fetchCandidates(
        PixabayClient $pixabayClient,
        VerseCardSession $session,
        array $usedPixabayIds,
        int $limit
    ): array {
        $candidates = [];
        $currentPage = $this->page;
        $maxPages = 50; // Pixabay API limit

        while (count($candidates) < $limit && $currentPage <= $maxPages) {
            $params = $this->buildSearchParams($session, $currentPage);

            try {
                $response = $pixabayClient->search($params);
            } catch (\Throwable $e) {
                Log::error('Pixabay search failed', [
                    'sessionId' => $this->sessionId,
                    'page' => $currentPage,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $hits = $response['hits'] ?? [];
            if (empty($hits)) {
                Log::info('No more hits available from Pixabay', [
                    'sessionId' => $this->sessionId,
                    'page' => $currentPage,
                ]);
                break;
            }

            // Select candidates from this page
            $pageCount = count($candidates);
            $needed = $limit - $pageCount;
            $pageCandidates = $this->selectCandidateHits($hits, $usedPixabayIds, $needed);

            foreach ($pageCandidates as $hit) {
                $candidates[] = $hit;
                $usedPixabayIds[] = $hit['id'];
            }

            // Update session pagination cursor
            $session->pixabay_page = $currentPage;
            $session->pixabay_offset = count($pageCandidates);

            // Move to next page if we still need more candidates
            if (count($candidates) < $limit) {
                $currentPage++;
            }
        }

        return $candidates;
    }

    /**
     * Build search parameters from session keywords and theme.
     *
     * @param VerseCardSession $session
     * @param int $page
     * @return array
     */
    private function buildSearchParams(VerseCardSession $session, int $page = 1): array
    {
        $keywords = $session->keywords ?? [];
        $themeSlug = $session->theme_slug;

        // Combine theme and keywords into a query string
        $queryParts = [$themeSlug];
        foreach ($keywords as $keyword) {
            $queryParts[] = $keyword;
        }
        $query = implode(' ', $queryParts);

        return [
            'q' => $query,
            'page' => $page
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
     * Select candidate hits that are not already used.
     *
     * @param array $hits
     * @param array $usedIds
     * @param int $limit
     * @return array
     */
    private function selectCandidateHits(array $hits, array $usedIds, int $limit): array
    {
        $candidates = [];
        foreach ($hits as $hit) {
            if (! isset($hit['id'])) {
                continue;
            }
            if (in_array($hit['id'], $usedIds)) {
                continue;
            }
            $candidates[] = $hit;
            if (count($candidates) >= $limit) {
                break;
            }
        }
        return $candidates;
    }

    /**
     * Download web format image and create thumbnail.
     *
     * @param int $assetId
     * @return void
     */
    private function downloadWebFormatImage(int $assetId): void
    {
        $asset = VerseCardAsset::find($assetId);
        if (! $asset) {
            Log::error('Asset not found for web format download', ['assetId' => $assetId]);
            return;
        }

        $session = $asset->session;
        if ($session->expires_at && $session->expires_at->isPast()) {
            Log::warning('Session expired, skipping web format download', ['assetId' => $assetId]);
            return;
        }

        $webFormatUrl = $asset->web_format_url;
        $usedFallback = false;

        if (! $webFormatUrl) {
            // Fall back to remote_url if web_format_url is missing
            $webFormatUrl = $asset->remote_url;
            $usedFallback = true;
        }

        if (! $webFormatUrl) {
            Log::error('Asset missing both web_format_url and remote_url, cannot download', ['assetId' => $assetId]);
            return;
        }

        // If we fell back to remote_url, update the asset record
        if ($usedFallback) {
            Log::info('Using remote_url as fallback for web_format_url', ['assetId' => $assetId]);
            $asset->web_format_url = $webFormatUrl;
            $asset->save();
        }
        

        // Acquire per-asset Redis lock to prevent duplicate downloads
        $lock = Cache::lock('download_web_format_' . $asset->id, 300); // 5 minutes
        if (! $lock->get()) {
            Log::warning('Duplicate web format download detected, skipping', ['assetId' => $assetId]);
            return;
        }

        try {
            // Stream download to ephemeral/verse-cards/{sessionId}/c/{assetId}_web.jpg
            $disk = $asset->disk ?: 'ephemeral';
            $directory = 'verse-cards/' . $session->id . '/c';
            $filename = $asset->id . '_web.jpg';
            $path = $directory . '/' . $filename;

            // Ensure directory exists
            Storage::disk($disk)->makeDirectory($directory);

            // Download with sink
            $response = Http::timeout(60)
                ->retry(3, 100)
                ->sink(Storage::disk($disk)->path($path))
                ->get($webFormatUrl);

            if (! $response->successful()) {
                throw new \Exception('HTTP request failed with status ' . $response->status());
            }

            // Update asset with web format path
            $asset->path = $path;
            $asset->save();

            Log::info('Web format image downloaded', [
                'assetId' => $assetId,
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to download web format image', [
                'assetId' => $assetId,
                'webFormatUrl' => $webFormatUrl,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SearchAndPrepareCandidates job failed', [
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