<?php

namespace SzentirasHu\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;

class PixabayImageStorage
{
    /**
     * Base directory for Pixabay images.
     */
    private const BASE_DIRECTORY = 'pixabay-images';

    /**
     * Number of shards to distribute files across.
     */
    private const SHARD_COUNT = 1000;

    /**
     * Default disk for storing images.
     */
    private const DEFAULT_DISK = 'ephemeral';

    /**
     * Get the shard directory for a given Pixabay ID.
     */
    private function getShard(int $pixabayId): string
    {
        return (string) ($pixabayId % self::SHARD_COUNT);
    }

    /**
     * Build the full storage path for a Pixabay image of a given type.
     *
     * @param int $pixabayId
     * @param string $type One of 'web', 'thumb', 'full'
     * @return string Relative path within the storage disk
     */
    public function getImagePath(int $pixabayId, string $type): string
    {
        $shard = $this->getShard($pixabayId);
        $extension = 'jpg';

        return self::BASE_DIRECTORY . '/' . $shard . '/' . $pixabayId . '.' . $type . '.' . $extension;
    }

    /**
     * Download an image from a remote URL and store it at the given path if missing.
     *
     * @param string $remoteUrl
     * @param string $storagePath
     * @param string $disk
     * @param int $lockTimeout Seconds to hold the lock (default 5 minutes)
     * @return bool True if the image exists after the operation, false on failure
     */
    public function downloadIfMissing(
        string $remoteUrl,
        string $storagePath,
        string $disk = self::DEFAULT_DISK,
        int $lockTimeout = 300
    ): bool {
        $storage = Storage::disk($disk);

        // Already exists?
        if ($storage->exists($storagePath)) {
            return true;
        }

        // Acquire a lock to prevent duplicate downloads across sessions
        $lockKey = 'pixabay_download_' . md5($storagePath);
        $lock = Cache::lock($lockKey, $lockTimeout);

        if (! $lock->get()) {
            Log::warning('Duplicate download detected, waiting for other process', ['path' => $storagePath]);
            // Wait a short moment and check again
            sleep(2);
            if ($storage->exists($storagePath)) {
                return true;
            }
            // If still not exists, we cannot proceed because another process is downloading
            // but we could retry? For simplicity, we'll just fail.
            Log::error('Failed to acquire lock and file still missing', ['path' => $storagePath]);
            return false;
        }

        try {
            // Ensure the directory exists
            $directory = dirname($storagePath);
            $storage->makeDirectory($directory);

            // Download with sink
            $response = Http::timeout(60)
                ->retry(3, 100)
                ->sink($storage->path($storagePath))
                ->get($remoteUrl);

            if (! $response->successful()) {
                Log::error('HTTP request failed downloading Pixabay image', [
                    'url' => $remoteUrl,
                    'status' => $response->status(),
                    'path' => $storagePath,
                ]);
                // Delete the partially downloaded file if any
                if ($storage->exists($storagePath)) {
                    $storage->delete($storagePath);
                }
                return false;
            }

            Log::info('Pixabay image downloaded successfully', [
                'url' => $remoteUrl,
                'path' => $storagePath,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to download Pixabay image', [
                'url' => $remoteUrl,
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
            // Clean up any partial file
            if ($storage->exists($storagePath)) {
                $storage->delete($storagePath);
            }
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Get the storage disk instance.
     */
    public function disk(string $disk = self::DEFAULT_DISK): Filesystem
    {
        return Storage::disk($disk);
    }

    /**
     * Check if an image of a given type already exists in storage.
     */
    public function exists(int $pixabayId, string $type, string $disk = self::DEFAULT_DISK): bool
    {
        $path = $this->getImagePath($pixabayId, $type);
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Get the absolute filesystem path for an image (if needed for Imagine etc.).
     */
    public function absolutePath(int $pixabayId, string $type, string $disk = self::DEFAULT_DISK): ?string
    {
        $path = $this->getImagePath($pixabayId, $type);
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }
        return $storage->path($path);
    }
}