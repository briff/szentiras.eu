<?php

namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Services\PixabayClient;
use Illuminate\Support\Facades\Log;
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

        // Build params from session keywords/theme
        $params = $this->buildSearchParams($session);

        // Call PixabayClient->search()
        try {
            $response = $pixabayClient->search($params);
        } catch (\Throwable $e) {
            Log::error('Pixabay search failed', [
                'sessionId' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
            $session->status = VerseCardSessionStatus::Failed->value;
            $session->save();
            return;
        }

        // Select 4 hits not used in this session
        $hits = $response['hits'] ?? [];
        if (empty($hits)) {
            Log::warning('No hits returned from Pixabay', ['sessionId' => $this->sessionId]);
            $session->status = VerseCardSessionStatus::Failed->value;
            $session->save();
            return;
        }

        $usedPixabayIds = $this->getUsedPixabayIds($session);
        $candidateHits = $this->selectCandidateHits($hits, $usedPixabayIds, 4);

        if (count($candidateHits) < 4) {
            Log::warning('Not enough unique hits available', [
                'sessionId' => $this->sessionId,
                'available' => count($candidateHits),
            ]);
            // We could still proceed with fewer, but for simplicity we fail.
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
                'disk' => 'ephemeral',
                'expires_at' => $session->expires_at,
            ]);
            $asset->save();
            $assetIds[] = $asset->id;
        }

        // Dispatch 4 DownloadCandidateImage jobs
        foreach ($assetIds as $assetId) {
            DownloadCandidateImage::dispatch($assetId)->onQueue('image-download');
        }

        // Update session cursor
        $session->pixabay_page = $this->page;
        $session->pixabay_offset = count($candidateHits);
        // If offset reaches per_page limit, move to next page (should not happen with only 4 hits)
        if ($session->pixabay_offset >= 50) {
            $session->pixabay_page++;
            $session->pixabay_offset = 0;
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
     * Build search parameters from session keywords and theme.
     *
     * @param VerseCardSession $session
     * @return array
     */
    private function buildSearchParams(VerseCardSession $session): array
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
            'page' => $this->page,
            'safesearch' => true,
            'image_type' => 'photo',
            'orientation' => 'horizontal',
            'per_page' => 50,
            'order' => 'popular',
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