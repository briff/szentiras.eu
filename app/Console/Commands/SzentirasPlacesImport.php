<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SzentirasPlacesImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'szentiras:places-import
                            {file? : Path to JSONL file (default: storage/app/private/geoimport/ancient.jsonl)}
                            {--github : Download the file from GitHub}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import geographic places from JSONL file into database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('github')) {
            $filePath = $this->downloadFromGitHub();
            if (!$filePath) {
                return;
            }
        } else {
            $filePath = $this->argument('file') ?? storage_path('geoimport/ancient.jsonl');
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("File not found or unreadable: {$filePath}");
            return;
        }

        $this->info("Importing places from {$filePath}");

        // Count total lines for progress bar
        $totalLines = 0;
        $tempHandle = fopen($filePath, 'r');
        if ($tempHandle) {
            while (fgets($tempHandle) !== false) {
                $totalLines++;
            }
            fclose($tempHandle);
        }

        $lineCount = 0;
        $placeCount = 0;
        $verseCount = 0;

        $progressBar = $this->output->createProgressBar($totalLines);
        $progressBar->start();

        // Open file
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error('Failed to open file.');
            return;
        }

        try {
            // Disable query logging to prevent memory accumulation
            $loggingEnabled = DB::logging();
            DB::disableQueryLog();

            // Process in chunks to avoid memory exhaustion
            while (($line = fgets($handle)) !== false) {
                $lineCount++;
                $progressBar->advance();

                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $data = json_decode($line, true);
                if (!$data) {
                    $this->warn("Skipping invalid JSON on line {$lineCount}");
                    continue;
                }

                // Extract fields
                $externalId = $data['id'] ?? null;
                if (!$externalId) {
                    $this->warn("Missing 'id' on line {$lineCount}");
                    continue;
                }

                $type = $data['types'][0] ?? 'unknown';
                $friendlyId = $data['friendly_id'] ?? '';
                $comment = $data['comment'] ?? null;

                // Extract lon_lat from first resolution
                $lonlat = null;
                if (isset($data['identifications'][0]['resolutions'][0]['lonlat'])) {
                    $lonlat = $data['identifications'][0]['resolutions'][0]['lonlat'];
                }

                // Process each place in its own transaction
                DB::transaction(function () use ($externalId, $type, $friendlyId, $comment, $lonlat, $data, &$placeCount, &$verseCount) {
                    // Upsert place using raw query to avoid Eloquent memory accumulation
                    $placeId = DB::table('places')
                        ->where('external_id', $externalId)
                        ->value('id');

                    if ($placeId) {
                        DB::table('places')
                            ->where('id', $placeId)
                            ->update([
                                'type' => $type,
                                'friendly_id' => $friendlyId,
                                'comment' => $comment,
                                'lon_lat' => $lonlat,
                                'updated_at' => now(),
                            ]);
                    } else {
                        $placeId = DB::table('places')->insertGetId([
                            'external_id' => $externalId,
                            'type' => $type,
                            'friendly_id' => $friendlyId,
                            'comment' => $comment,
                            'lon_lat' => $lonlat,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $placeCount++;

                    // Delete existing verse references for this place (to avoid duplicates)
                    DB::table('place_verse')->where('place_id', $placeId)->delete();

                    // Process verses
                    $verses = $data['verses'] ?? [];
                    foreach ($verses as $verseData) {
                        $usx = $verseData['usx'] ?? null;
                        if (!$usx) {
                            continue;
                        }

                        // Parse USX string like "NUM 27:12"
                        $parts = explode(' ', $usx, 2);
                        if (count($parts) !== 2) {
                            continue;
                        }
                        $bookCode = $parts[0];
                        $chapterVerse = $parts[1];
                        [$chapter, $verse] = explode(':', $chapterVerse) + [1, 1];

                        DB::table('place_verse')->insert([
                            'place_id' => $placeId,
                            'book_code' => $bookCode,
                            'chapter_number' => (int) $chapter,
                            'verse_number' => (int) $verse,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $verseCount++;
                    }
                });
            }

            // Restore query logging state
            if ($loggingEnabled) {
                DB::enableQueryLog();
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Processed {$lineCount} lines, imported/updated {$placeCount} places with {$verseCount} verse references.");
    }

    private function downloadFromGitHub(): ?string
    {
        $url = 'https://raw.githubusercontent.com/briff/Bible-Geocoding-Data/refs/heads/main/data/ancient.jsonl';
        $storageDir = 'geoimport';
        $fileName = 'ancient.jsonl';

        try {
            $this->info("Downloading from {$url}");
            $fileContents = Http::get($url)->body();
            Storage::put("{$storageDir}/{$fileName}", $fileContents);
            $this->info("File downloaded successfully to " . Storage::path("{$storageDir}/{$fileName}"));
            return Storage::path("{$storageDir}/{$fileName}");
        } catch (\Exception $e) {
            $this->error("Failed to download file: {$e->getMessage()}");
            return null;
        }
    }
}
