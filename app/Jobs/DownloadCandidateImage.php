<?php

namespace SzentirasHu\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Service\Imagine\ImagineFacade as Imagine;

class DownloadCandidateImage extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The asset ID.
     *
     * @var int
     */
    protected int $assetId;

    /**
     * Create a new job instance.
     *
     * @param int $assetId
     */
    public function __construct(int $assetId)
    {
        $this->assetId = $assetId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('DownloadCandidateImage started', ['assetId' => $this->assetId]);

        $asset = VerseCardAsset::find($this->assetId);
        if (! $asset) {
            Log::error('Asset not found', ['assetId' => $this->assetId]);
            return;
        }

        // Abort if session expired
        $session = $asset->session;
        if ($session->expires_at && $session->expires_at->isPast()) {
            Log::warning('Session expired, skipping download', ['assetId' => $this->assetId]);
            $asset->state = 'deleted';
            $asset->save();
            return;
        }

        if ($asset->state !== 'queued') {
            Log::warning('Asset state is not queued', [
                'assetId' => $this->assetId,
                'state' => $asset->state,
            ]);
            return;
        }

        $remoteUrl = $asset->remote_url;
        if (! $remoteUrl) {
            Log::error('Asset missing remote_url', ['assetId' => $this->assetId]);
            $this->markAssetFailed($asset, 'Missing remote URL');
            return;
        }

        // Acquire per-asset Redis lock to prevent duplicate downloads
        $lock = Cache::lock('download_asset_' . $asset->id, 300); // 5 minutes
        if (! $lock->get()) {
            Log::warning('Duplicate download detected, skipping', ['assetId' => $this->assetId]);
            return;
        }

        // Update state to downloading
        $asset->state = 'downloading';
        $asset->save();

        try {
            // Stream download to ephemeral/verse-cards/{sessionId}/c/{assetId}.jpg
            $disk = $asset->disk ?: 'ephemeral';
            $directory = 'verse-cards/' . $session->id . '/c';
            $filename = $asset->id . '.jpg';
            $path = $directory . '/' . $filename;

            // Ensure directory exists
            Storage::disk($disk)->makeDirectory($directory);

            // Download with sink
            $response = Http::timeout(60)
                ->retry(3, 100)
                ->sink(Storage::disk($disk)->path($path))
                ->get($remoteUrl);

            if (! $response->successful()) {
                throw new \Exception('HTTP request failed with status ' . $response->status());
            }

            // Get file size
            $bytes = Storage::disk($disk)->size($path);

            // Create thumbnail .../{assetId}_t.jpg (max width 520) using Imagick (fast resize)
            $thumbPath = $this->generateThumbnail($disk, $path, $asset);

            // Update asset
            $asset->path = $path;
            $asset->thumb_path = $thumbPath;
            $asset->bytes = $bytes;
            $asset->state = 'ready';
            $asset->save();

            Log::info('DownloadCandidateImage completed', [
                'assetId' => $this->assetId,
                'path' => $path,
                'thumb' => $thumbPath,
                'bytes' => $bytes,
            ]);

            // Transition session to Choosing once all candidate assets for this batch are ready
            $this->transitionToChoosingIfReady($session);
        } catch (\Throwable $e) {
            Log::error('Failed to download or process image', [
                'assetId' => $this->assetId,
                'remoteUrl' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);
            $this->markAssetFailed($asset, 'Download failed: ' . $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    /**
     * Transition session from Downloading to Choosing when all queued candidates are ready.
     */
    private function transitionToChoosingIfReady(VerseCardSession $session): void
    {
        $session->refresh();

        if ($session->status !== VerseCardSessionStatus::Downloading->value) {
            return;
        }

        $pendingCount = $session->assets()
            ->where('kind', 'candidate')
            ->whereIn('state', ['queued', 'downloading'])
            ->count();

        if ($pendingCount === 0) {
            $session->status = VerseCardSessionStatus::Choosing->value;
            $session->save();

            Log::info('Session transitioned to choosing', ['sessionId' => $session->id]);
        }
    }

    /**
     * Generate thumbnail with max width 520 using Imagick (via Imagine).
     *
     * @param string $disk
     * @param string $originalPath
     * @param VerseCardAsset $asset
     * @return string|null
     */
    private function generateThumbnail(string $disk, string $originalPath, VerseCardAsset $asset): ?string
    {
        try {
            $imagine = app('imagine');
            $image = $imagine->open(Storage::disk($disk)->path($originalPath));

            // Calculate dimensions preserving aspect ratio, max width 520
            $size = $image->getSize();
            $width = $size->getWidth();
            $height = $size->getHeight();
            $targetWidth = 520;
            if ($width <= $targetWidth) {
                // No need to resize, just copy? We'll still create thumbnail.
                $targetWidth = $width;
                $targetHeight = $height;
            } else {
                $targetHeight = (int) ($height * $targetWidth / $width);
            }

            $thumbnail = $image->thumbnail(
                new \Imagine\Image\Box($targetWidth, $targetHeight),
                \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND
            );

            $thumbFilename = $asset->id . '_t.jpg';
            $thumbPath = 'verse-cards/' . $asset->session_id . '/c/' . $thumbFilename;
            $thumbnail->save(Storage::disk($disk)->path($thumbPath), ['quality' => 85]);

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::error('Failed to generate thumbnail', [
                'assetId' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mark asset as failed.
     *
     * @param VerseCardAsset $asset
     * @param string $reason
     * @return void
     */
    private function markAssetFailed(VerseCardAsset $asset, string $reason): void
    {
        $asset->state = 'failed';
        $asset->save();
        Log::error('Asset marked as failed', [
            'assetId' => $asset->id,
            'reason' => $reason,
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
        Log::error('DownloadCandidateImage job failed', [
            'assetId' => $this->assetId,
            'error' => $exception->getMessage(),
        ]);

        $asset = VerseCardAsset::find($this->assetId);
        if ($asset) {
            $asset->state = 'failed';
            $asset->save();
        }
    }
}