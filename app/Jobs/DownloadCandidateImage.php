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
use SzentirasHu\Services\PixabayImageStorage;

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
     * @param PixabayImageStorage $imageStorage
     * @return void
     */
    public function handle(PixabayImageStorage $imageStorage): void
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
            Log::warning('Session expired, skipping resize', ['assetId' => $this->assetId]);
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

        // Check if web format image exists
        $disk = $asset->disk ?: 'ephemeral';
        if (! $asset->path || ! Storage::disk($disk)->exists($asset->path)) {
            Log::error('Web format image missing', ['assetId' => $this->assetId]);
            $this->markAssetFailed($asset, 'Web format image not found');
            return;
        }

        // Acquire per-asset Redis lock to prevent duplicate processing
        $lock = Cache::lock('resize_asset_' . $asset->id, 300); // 5 minutes
        if (! $lock->get()) {
            Log::warning('Duplicate resize detected, skipping', ['assetId' => $this->assetId]);
            return;
        }

        // Update state to downloading (processing)
        $asset->state = 'downloading';
        $asset->save();

        try {
            // Create thumbnail from web format image: .../{assetId}_t.jpg (max width 520)
            $thumbPath = $this->generateThumbnail($disk, $asset->path, $asset, $imageStorage);

            // Update asset
            $asset->thumb_path = $thumbPath;
            $asset->state = 'ready';
            $asset->save();

            Log::info('DownloadCandidateImage completed', [
                'assetId' => $this->assetId,
                'path' => $asset->path,
                'thumb' => $thumbPath,
            ]);

            // Transition session to Choosing once all candidate assets for this batch are ready
            $this->transitionToChoosingIfReady($session);
        } catch (\Throwable $e) {
            Log::error('Failed to process image', [
                'assetId' => $this->assetId,
                'error' => $e->getMessage(),
            ]);
            $this->markAssetFailed($asset, 'Processing failed: ' . $e->getMessage());
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
     * @param PixabayImageStorage $imageStorage
     * @return string|null
     */
    private function generateThumbnail(string $disk, string $originalPath, VerseCardAsset $asset, PixabayImageStorage $imageStorage): ?string
    {
        // Determine thumbnail path based on Pixabay ID
        if (! $asset->pixabay_id) {
            Log::error('Asset missing pixabay_id, cannot store thumbnail in common folder', ['assetId' => $asset->id]);
            return null;
        }

        $thumbPath = $imageStorage->getImagePath($asset->pixabay_id, 'thumb');
        $storage = Storage::disk($disk);

        // If thumbnail already exists, return its path
        if ($storage->exists($thumbPath)) {
            Log::info('Thumbnail already exists in common storage', ['assetId' => $asset->id, 'path' => $thumbPath]);
            return $thumbPath;
        }

        try {
            $imagine = app('imagine');
            $image = $imagine->open($storage->path($originalPath));

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

            // Ensure directory exists
            $storage->makeDirectory(dirname($thumbPath));

            $thumbnail->save($storage->path($thumbPath), ['quality' => 85]);

            Log::info('Thumbnail generated and saved to common storage', [
                'assetId' => $asset->id,
                'path' => $thumbPath,
            ]);
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