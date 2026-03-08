<?php

namespace SzentirasHu\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Models\DailyReading;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\NumberingSchemeService;
use SzentirasHu\Service\Reference\ParsingException;

class DailyReadingService
{
    /** Base URL for the napi-lelki-batyu JSON API */
    private const API_BASE_URL = 'https://szentjozsefhackathon.github.io/napi-lelki-batyu/';

    public function __construct(
        private readonly NumberingSchemeService $numberingSchemeService,
    ) {}

    /**
     * Fetch and parse the daily reading data for the given date, storing it
     * in the database. Returns the DailyReading record on success, or null on
     * failure.
     */
    public function fetchAndStore(\DateTimeInterface $date): ?DailyReading
    {
        $dateString = $date->format('Y-m-d');
        $url = self::API_BASE_URL . $dateString . '.json';

        /** @var DailyReading $record */
        $record = DailyReading::updateOrCreate(
            ['date' => $dateString],
            ['status' => DailyReading::STATUS_FETCHING, 'error_message' => null],
        );

        try {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()} fetching {$url}");
            }

            $data = $response->json();

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid JSON response from daily reading API.');
            }

            [$celebrationName, $rawParts, $processedRefs] = $this->parseResponse($data);

            $record->celebration_name = $celebrationName;
            $record->raw_parts = $rawParts;
            $record->processed_refs = $processedRefs;
            $record->status = DailyReading::STATUS_FETCHED;
            $record->error_message = null;
            $record->save();

            Log::info("Daily reading for {$dateString} fetched successfully.", [
                'celebration' => $celebrationName,
                'ref_count' => count($processedRefs),
            ]);

            return $record;
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch daily reading for {$dateString}: {$e->getMessage()}");

            $record->status = DailyReading::STATUS_FAILED;
            $record->error_message = $e->getMessage();
            $record->save();

            return null;
        }
    }

    /**
     * Parse the JSON response and extract celebration name, raw parts, and
     * processed (converted) reference strings from celebrationKey 0.
     *
     * @return array{0: string|null, 1: array, 2: string[]}
     * @throws \RuntimeException
     */
    private function parseResponse(array $data): array
    {
        $celebrations = $data['celebration'] ?? null;

        if (!is_array($celebrations) || empty($celebrations)) {
            throw new \RuntimeException('No celebrations found in daily reading response.');
        }

        // Find celebration with celebrationKey 0
        $celebration = null;
        foreach ($celebrations as $item) {
            if (isset($item['celebrationKey']) && (int) $item['celebrationKey'] === 0) {
                $celebration = $item;
                break;
            }
        }

        if ($celebration === null) {
            // Fall back to first celebration
            $celebration = $celebrations[0];
        }

        $celebrationName = $celebration['name'] ?? null;
        $rawParts = $celebration['parts'] ?? [];

        // Flatten nested arrays (e.g. multiple forms of the same reading offered as an array)
        // and skip alternative/short forms identified by a "cause" key.
        $flatParts = [];
        foreach ($rawParts as $part) {
            if (isset($part[0])) {
                // Nested array of reading variants – keep only the first (full) form
                foreach ($part as $variant) {
                    if (!isset($variant['cause'])) {
                        $flatParts[] = $variant;
                        break;
                    }
                }
            } else {
                $flatParts[] = $part;
            }
        }

        // Filter parts that have a non-null ref and are not purely verse texts
        $relevantParts = array_values(array_filter($flatParts, function (array $part): bool {
            return !empty($part['ref']);
        }));

        $processedRefs = [];
        foreach ($relevantParts as $part) {
            $ref = $this->cleanRef($part['ref']);
            if (empty($ref)) {
                continue;
            }

            $convertedRef = $this->convertRef($ref);
            if ($convertedRef !== null) {
                $processedRefs[] = $convertedRef;
            }
        }

        if (empty($processedRefs)) {
            throw new \RuntimeException('No valid references found in daily reading parts.');
        }

        return [$celebrationName, $relevantParts, $processedRefs];
    }

    /**
     * Clean a raw reference string from the API:
     * - Takes the first option before " vagy:" / " vagy " (alternatives)
     * - Replaces " és " with "." (verse list separator)
     * - Strips surrounding whitespace
     */
    public function cleanRef(string $ref): string
    {
        // Remove alternative readings - keep only part before "vagy:" or "vagy "
        $ref = preg_replace('/ vagy:.*$/iu', '', $ref) ?? $ref;
        $ref = preg_replace('/ vagy .*$/iu', '', $ref) ?? $ref;

        // Replace "és" (and) between verse numbers with "."
        $ref = preg_replace('/ és /iu', '.', $ref) ?? $ref;

        return trim($ref);
    }

    /**
     * Convert a cleaned reference string from Vulgata psalm numbering to the
     * default numbering scheme. Returns null if the reference cannot be parsed.
     */
    public function convertRef(string $ref): ?string
    {
        try {
            $canonical = CanonicalReference::fromString($ref);
            $converted = $this->numberingSchemeService->convertReference($canonical, 'vulgata');
            // Remove spaces for URL-safe storage
            return str_replace(' ', '', $converted->toString());
        } catch (ParsingException $e) {
            Log::warning("Could not parse daily reading reference '{$ref}': {$e->getMessage()}");
            return null;
        }
    }
}
