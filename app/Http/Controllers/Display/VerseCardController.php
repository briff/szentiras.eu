<?php
/**
 * Verse card controller for creating Bible verse images.
 */

namespace SzentirasHu\Http\Controllers\Display;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Theme\ThemeService;
use SzentirasHu\Jobs\SearchAndPrepareCandidates;
use SzentirasHu\Jobs\RenderVerseCardJob;
use Illuminate\Support\Str;
use Storage;

class VerseCardController extends Controller
{
    public function __construct(
        private TextService $textService,
        private TranslationRepository $translationRepository,
        private ThemeService $themeService,
    ) {}

    /**
     * Return HTML dialog with list of verses for selection.
     */
    public function getDialog($translationAbbrev, $refString): View
    {
        $translation = $this->translationRepository->getByAbbrev($translationAbbrev);
        $ref = CanonicalReference::fromString($refString, $translation->id);
        $verseContainers = $this->textService->getTranslatedVerses($ref, $translation);

        // Flatten verses for easy display
        $verses = [];
        foreach ($verseContainers as $container) {
            foreach ($container->getParsedVerses() as $verseData) {
                $reference = $verseData->book->abbrev . $verseData->chapter . ',' . $verseData->numv;
                $verses[] = [
                    'gepi' => $verseData->gepi ?? null,
                    'reference' => $reference,
                    'text' => $verseData->getText('none'),
                ];
            }
        }

        return view('textDisplay.verseCardDialog')->with([
            'refString' => $refString,
            'translationId' => $translation->id,
            'translationAbbrev' => $translationAbbrev,
            'verses' => $verses,
        ]);
    }

    /**
     * Find similar themes for selected verses (POST).
     */
    public function findThemes(Request $request): JsonResponse
    {
        $request->validate([
            'selectedVerses' => 'required|string',
            'translationAbbrev' => 'required|string',
        ]);

        $selectedVerses = $request->input('selectedVerses');
        $translationAbbrev = $request->input('translationAbbrev');

        $themes = $this->themeService->findSimilarThemes(
            $selectedVerses,
            $translationAbbrev,
            10
        );

        return response()->json([
            'success' => true,
            'themes' => $themes,
        ]);
    }

    /**
     * Create a new verse card session.
     */
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'verse_refs' => 'required|array',
            'verse_refs.*' => 'required|string',
            'verse_texts' => 'required|array',
            'verse_texts.*' => 'required|string',
            'theme_id' => 'required|integer|exists:themes,id',
            'keywords' => 'nullable|array',
        ]);

        $theme = Theme::findOrFail($request->input('theme_id'));

        $sessionId = Str::uuid()->toString();

        // Format verse references with common prefix compression
        $verseRefs = $request->input('verse_refs');
        $formattedRef = $this->formatVerseReferences($verseRefs);

        // Concatenate verse texts with space
        $verseTexts = $request->input('verse_texts');
        $concatenatedText = implode(' ', $verseTexts);

        $session = VerseCardSession::create([
            'id' => $sessionId,
            'user_id' => auth()->id(),
            'verse_ref' => $formattedRef,
            'verse_text' => $concatenatedText,
            'theme_slug' => $theme->id,
            'keywords' => $request->input('keywords', []),
            'status' => VerseCardSessionStatus::Initializing->value,
            'pixabay_page' => 1,
            'pixabay_offset' => 0,
            'expires_at' => now()->addHours(24),
        ]);

        // Dispatch job to search and prepare initial candidates
        SearchAndPrepareCandidates::dispatch($session->id);

        return response()->json([
            'session_id' => $session->id,
            'status' => 'initializing',
        ]);
    }

    /**
     * Display the verse card creator page.
     */
    public function showCreator(string $sessionId): View
    {
        $session = VerseCardSession::findOrFail($sessionId);

        return view('textDisplay.verseCardCreator', [
            'sessionId' => $sessionId,
            'session' => $session,
        ]);
    }

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
        if ($session->status === VerseCardSessionStatus::Ready->value) {
            /** @var VerseCardAsset|null $finalAsset */
            $finalAsset = $session->assets()
                ->where('kind', 'final')
                ->where('state', 'ready')
                ->latest()
                ->first();

            if ($finalAsset) {
                return response()->json([
                    'status' => VerseCardSessionStatus::Ready->value,
                    'final_url' => $this->getAssetUrl($finalAsset),
                    'download_url' => route('verse-card.download', ['sessionId' => $session->id]),
                ]);
            }
        }

        if ($session->status === VerseCardSessionStatus::Downloading->value) {
            $pendingAssets = $session->assets()
                ->where('kind', 'candidate')
                ->latest()
                ->limit(4)
                ->get()
                ->map(fn(VerseCardAsset $asset) => [
                    'id' => $asset->id,
                    'state' => $asset->state,
                    'pixabay_id' => $asset->pixabay_id,
                    'pixabay_user' => $asset->pixabay_user,
                    'pixabay_page_url' => $asset->pixabay_page_url,
                    'thumb_url' => $asset->state === 'ready' ? $this->getAssetUrl($asset, 'thumb') : null,
                ]);

            return response()->json([
                'status' => VerseCardSessionStatus::Downloading->value,
                'candidates' => $pendingAssets->values(),
            ]);
        }

        if ($session->status === VerseCardSessionStatus::Choosing->value) {
            $candidates = $session->assets()
                ->where('kind', 'candidate')
                ->where('state', 'ready')
                ->latest()
                ->limit(4)
                ->get()
                ->map(fn(VerseCardAsset $asset) => [
                    'id' => $asset->id,
                    'thumb_url' => $this->getAssetUrl($asset, 'thumb'),
                    'pixabay_id' => $asset->pixabay_id,
                    'pixabay_user' => $asset->pixabay_user,
                    'pixabay_page_url' => $asset->pixabay_page_url,
                ]);

            return response()->json([
                'status' => VerseCardSessionStatus::Choosing->value,
                'candidates' => $candidates->values(),
            ]);
        }

        if ($session->status === VerseCardSessionStatus::Failed->value) {
            return response()->json([
                'status' => VerseCardSessionStatus::Failed->value,
                'message' => 'Hiba történt a jelöltek keresése közben.',
            ], 422);
        }

        if ($session->status === VerseCardSessionStatus::Ended->value) {
            return response()->json([
                'status' => VerseCardSessionStatus::Ended->value,
                'message' => 'Véget ért a munkamenet',
            ], 422);
        }


        // Still processing
        return response()->json([
            'status' => VerseCardSessionStatus::from($session->status)->value,
        ]);
    }

    /**
     * Request more candidate images.
     */
    public function requestMore(string $sessionId): JsonResponse
    {
        $session = VerseCardSession::findOrFail($sessionId);

        if ($session->status === VerseCardSessionStatus::Failed->value) {
            return response()->json([
                'status' => VerseCardSessionStatus::Failed->value,
                'message' => 'Nem lehet több jelöltet keresni hiba után.',
            ], 422);
        }

        // Increment pagination and go back to downloading state
        $session->update([
            'pixabay_offset' => $session->pixabay_offset + 4,
            'status' => VerseCardSessionStatus::Downloading->value,
        ]);

        // Dispatch job to search more candidates
        SearchAndPrepareCandidates::dispatch($session->id);

        // Return current status (will be polling)
        return response()->json([
            'status' => 'processing',
        ]);
    }

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
            'status' => VerseCardSessionStatus::Rendering->value,
        ]);

        // Mark other candidates as unselected
        $session->assets()
            ->where('kind', 'candidate')
            ->update(['state' => 'unselected']);

        $candidateAsset->update(['state' => 'selected']);

        // Dispatch job to render final card
        RenderVerseCardJob::dispatch($session->id, $candidateAsset->id);

        // Return processing status (will be polling)
        return response()->json([
            'status' => 'processing',
        ]);
    }

    /**
     * Update session verse text/ref and trigger re-render.
     */
    public function updateAndRender(string $sessionId, Request $request): JsonResponse
    {
        $request->validate([
            'verse_ref' => 'required|string|max:255',
            'verse_text' => 'required|string',
        ]);

        $session = VerseCardSession::findOrFail($sessionId);

        // Update session with new verse data
        $session->update([
            'verse_ref' => $request->input('verse_ref'),
            'verse_text' => $request->input('verse_text'),
            'status' => VerseCardSessionStatus::Rendering->value,
        ]);

        // Get the selected candidate asset
        $selectedAsset = $session->assets()
            ->where('kind', 'candidate')
            ->where('state', 'selected')
            ->first();

        if (!$selectedAsset) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nem található a kiválasztott kép.',
            ], 422);
        }

        // Dispatch job to render final card with updated text
        RenderVerseCardJob::dispatch($session->id, $selectedAsset->id);

        return response()->json([
            'status' => 'rendering',
        ]);
    }

    /**
     * Download the final verse card image.
     */
    public function download(string $sessionId)
    {
        $session = VerseCardSession::findOrFail($sessionId);

        /** @var VerseCardAsset $finalAsset */
        $finalAsset = $session->assets()
            ->where('kind', 'final')
            ->where('state', 'ready')
            ->latest()
            ->firstOrFail();

        // Option 1: Stream from local/ephemeral disk storage
        if (in_array($finalAsset->disk, ['ephemeral', 'local'], true)) {
            return response()->download(
                Storage::disk($finalAsset->disk)->path($finalAsset->path),
                'verse-card-' . $session->id . '.jpg'
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

    /**
     * End session and cleanup resources.
     */
    public function endSession(string $sessionId): JsonResponse
    {
        $session = VerseCardSession::findOrFail($sessionId);

        // Mark as ended
        $session->update(['status' => VerseCardSessionStatus::Ended->value]);

        return response()->json([
            'status' => 'ended',
        ]);
    }

    /**
     * Serve an asset file from ephemeral (or local) disk storage.
     */
    public function serveAsset(int $assetId, string $type = 'full')
    {
        $asset = VerseCardAsset::findOrFail($assetId);

        $path = $type === 'thumb' ? $asset->thumb_path : $asset->path;

        if (!$path || !in_array($asset->disk, ['ephemeral', 'local'], true)) {
            abort(404);
        }

        if (!Storage::disk($asset->disk)->exists($path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk($asset->disk)->path($path),
            ['Content-Type' => 'image/jpeg']
        );
    }

    /**
     * Get asset URL for display.
     */
    private function getAssetUrl(VerseCardAsset $asset, string $type = 'full'): string
    {
        $path = $type === 'thumb' ? $asset->thumb_path : $asset->path;

        if (!$path) {
            return '';
        }

        if (in_array($asset->disk, ['ephemeral', 'local'], true)) {
            return route('verse-card.asset', ['assetId' => $asset->id, 'type' => $type]);
        }

        if ($asset->disk === 's3') {
            return Storage::disk('s3')->url($path);
        }

        return $asset->remote_url ?? '';
    }

    /**
     * Format verse references with common prefix compression.
     * E.g., ['Mt6,2', 'Mt6,3'] becomes 'Mt 6,2-3'
     * If verses are not consecutive, uses '.' instead of '-'
     * E.g., ['Mt6,2', 'Mt6,5'] becomes 'Mt 6,2.5'
     */
    private function formatVerseReferences(array $verseRefs): string
    {
        if (empty($verseRefs)) {
            return '';
        }

        if (count($verseRefs) === 1) {
            return $verseRefs[0];
        }

        // Parse references to extract book, chapter, and verse
        $parsed = [];
        foreach ($verseRefs as $ref) {
            // Match pattern like "Mt6,2" or "Mt 6,2"
            if (preg_match('/^([A-Za-z]+)\s*(\d+),(\d+)$/', $ref, $matches)) {
                $parsed[] = [
                    'book' => $matches[1],
                    'chapter' => $matches[2],
                    'verse' => (int) $matches[3],
                    'original' => $ref,
                ];
            } else {
                // If parsing fails, just return the original reference
                $parsed[] = [
                    'original' => $ref,
                ];
            }
        }

        // Check if all references have the same book and chapter
        $firstParsed = $parsed[0];
        if (!isset($firstParsed['book']) || !isset($firstParsed['chapter'])) {
            // If we can't parse, just join with semicolon
            return implode('; ', $verseRefs);
        }

        $allSameBookChapter = true;
        foreach ($parsed as $p) {
            if (!isset($p['book']) || !isset($p['chapter']) ||
                $p['book'] !== $firstParsed['book'] ||
                $p['chapter'] !== $firstParsed['chapter']) {
                $allSameBookChapter = false;
                break;
            }
        }

        if ($allSameBookChapter) {
            // All same book and chapter: compress verses
            $verses = array_map(fn($p) => $p['verse'], $parsed);
            $compressed = $this->compressVerseNumbers($verses);
            return $firstParsed['book'] . ' ' . $firstParsed['chapter'] . ',' . $compressed;
        }

        // Different books or chapters: join with semicolon
        return implode('; ', $verseRefs);
    }

    /**
     * Compress verse numbers using '-' for consecutive verses and '.' for non-consecutive.
     * E.g., [2, 3, 4] becomes '2-4'
     * E.g., [2, 4, 5] becomes '2.4-5'
     */
    private function compressVerseNumbers(array $verses): string
    {
        if (empty($verses)) {
            return '';
        }

        if (count($verses) === 1) {
            return (string) $verses[0];
        }

        // Sort and remove duplicates
        $verses = array_unique($verses);
        sort($verses);

        $groups = [];
        $currentGroup = [$verses[0]];

        for ($i = 1; $i < count($verses); $i++) {
            if ($verses[$i] === $currentGroup[count($currentGroup) - 1] + 1) {
                // Consecutive: add to current group
                $currentGroup[] = $verses[$i];
            } else {
                // Not consecutive: start new group
                $groups[] = $currentGroup;
                $currentGroup = [$verses[$i]];
            }
        }
        $groups[] = $currentGroup;

        // Format groups: single verse as-is, consecutive as "start-end", non-consecutive as "start.end"
        $formatted = [];
        foreach ($groups as $group) {
            if (count($group) === 1) {
                $formatted[] = (string) $group[0];
            } else {
                // Multiple verses in group: use '-' for consecutive
                $formatted[] = $group[0] . '-' . $group[count($group) - 1];
            }
        }

        // Join groups with '.'
        return implode('.', $formatted);
    }
}