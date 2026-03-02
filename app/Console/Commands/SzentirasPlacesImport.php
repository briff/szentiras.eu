<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SzentirasHu\Data\Entity\Place;
use SzentirasHu\Data\Entity\PlaceVerse;

class SzentirasPlacesImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'szentiras:places-import
                            {file? : Path to JSONL file (default: storage/app/private/geoimport/ancient.jsonl)}';

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
        $filePath = $this->argument('file') ?? storage_path('app/private/geoimport/ancient.jsonl');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("File not found or unreadable: {$filePath}");
            return;
        }

        $this->info("Importing places from {$filePath}");

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error('Failed to open file.');
            return;
        }

        $lineCount = 0;
        $placeCount = 0;
        $verseCount = 0;

        DB::transaction(function () use ($handle, &$lineCount, &$placeCount, &$verseCount) {
            while (($line = fgets($handle)) !== false) {
                $lineCount++;
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

                // Upsert place
                $place = Place::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'type' => $type,
                        'friendly_id' => $friendlyId,
                        'comment' => $comment,
                        'lon_lat' => $lonlat,
                    ]
                );

                $placeCount++;

                // Delete existing verse references for this place (to avoid duplicates)
                PlaceVerse::where('place_id', $place->id)->delete();

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

                    PlaceVerse::create([
                        'place_id' => $place->id,
                        'book_code' => $bookCode,
                        'chapter_number' => (int) $chapter,
                        'verse_number' => (int) $verse,
                    ]);
                    $verseCount++;
                }
            }
        });

        fclose($handle);

        $this->info("Processed {$lineCount} lines, imported/updated {$placeCount} places with {$verseCount} verse references.");
    }
}
