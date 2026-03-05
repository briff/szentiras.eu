<?php

namespace SzentirasHu\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Services\PixabayImageStorage;
use Throwable;

class RenderVerseCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $sessionId,
        public int $selectedAssetId,
        public array $style = []
    ) {
        $this->onQueue('card-render');
    }

    /**
     * Predefined output dimensions covering common aspect ratios.
     * Each dimension is [width, height] with a descriptive aspect ratio.
     * Ordered by common usage patterns.
     */
    private const PRESET_DIMENSIONS = [
        // Square
        [1000, 1000],   // 1:1
        
        // Portrait (taller than wide)
        [853, 1280],    // 2:3
        
        // Landscape (wider than tall)
        [1280, 960],    // 4:3
        [1280, 720],    // 16:9 (common video)
        [1280, 853],    // 3:2
        
        // Extreme aspect ratios
        [1280, 640],    // 2:1
        [640, 1280],    // 1:2
    ];

    // TODO: If we get fullHD pictures, we need calculate this based on actual image dimensions
    private const PAD_X      = 48;
    private const PAD_BOTTOM = 32;  // distance from image bottom to bottom of text block
    private const LINE_GAP   = 8;

    private const JPG_QUALITY = 88;

    private const FONT_VERSE = 'assets/fonts/NotoSerif-Regular.ttf';
    private const FONT_REF   = 'assets/fonts/NotoSans-Regular.ttf';

    public function handle(PixabayImageStorage $imageStorage): void
    {
        Log::info('RenderVerseCardJob started', [
            'sessionId' => $this->sessionId,
            'selectedAssetId' => $this->selectedAssetId,
        ]);
        try {
            /** @var VerseCardSession $session */
            $session = VerseCardSession::query()->whereKey($this->sessionId)->firstOrFail();

            if ($session->expires_at && $session->expires_at->isPast()) {
                $session->status = VerseCardSessionStatus::Expired->value;
                $session->save();
                return;
            }

            /** @var VerseCardAsset $selected */
            $selected = VerseCardAsset::query()
                ->whereKey($this->selectedAssetId)
                ->where('session_id', $session->id)
                ->where('state', 'selected')
                ->firstOrFail();

            $disk = $selected->disk ?: 'ephemeral';

            // Download full-size remote image if not already downloaded
            $remoteImagePath = $this->downloadRemoteImage($selected, $session, $disk, $imageStorage);
            if (!$remoteImagePath) {
                throw new \RuntimeException("Failed to download remote image");
            }

            if (!Storage::disk($disk)->exists($remoteImagePath)) {
                throw new \RuntimeException("Remote image missing: {$disk}:{$remoteImagePath}");
            }

            $verseText = $this->getVerseText($session);
            $refText   = $this->getReferenceText($session);

            $session->status = VerseCardSessionStatus::Rendering->value;
            $session->save();

            $imagine = new Imagine();

            $sourceAbs = Storage::disk($disk)->path($remoteImagePath);

            // 1) Load
            $img = $imagine->open($sourceAbs);

            // 2) Scale and preserve aspect ratio:
            // Select output dimensions from presets that best match source aspect ratio
            $srcSize = $img->getSize();
            $srcAspect = $srcSize->getWidth() / max(1, $srcSize->getHeight());
            
            [$outW, $outH] = $this->selectClosestDimensions($srcAspect);
            
            // Scale source to output dimensions using MAX scale to fill without padding
            $scaleW = $outW / $srcSize->getWidth();
            $scaleH = $outH / $srcSize->getHeight();
            $scale = max($scaleW, $scaleH); // Use max to zoom in and fill completely
            $scaledW = (int)ceil($srcSize->getWidth() * $scale);
            $scaledH = (int)ceil($srcSize->getHeight() * $scale);
            $img = $img->resize(new Box($scaledW, $scaledH));
            
            // Crop to exact output size from center
            $cropX = (int)floor(($scaledW - $outW) / 2);
            $cropY = (int)floor(($scaledH - $outH) / 2);
            $cropX = max(0, $cropX);
            $cropY = max(0, $cropY);
            
            // Ensure crop box doesn't exceed image bounds
            $cropW = min($outW, $scaledW - $cropX);
            $cropH = min($outH, $scaledH - $cropY);
            
            $img = $img->crop(new Point($cropX, $cropY), new Box($cropW, $cropH));
            
            // Store final dimensions for later use
            $finalW = $outW;
            $finalH = $outH;

            // Calculate panel height as a factor of final height
            $panelH = (int)($finalH / 2);

            // Ensure overlays exist for the actual output dimensions
            $this->ensureOverlaysExist($finalW, $finalH, $panelH);

            // 3) Apply vignette overlay (matches actual output dimensions)
            $vignetteAbs = $this->overlayPath("vignette_{$finalW}x{$finalH}.png");
            $vignette = $imagine->open($vignetteAbs);
            $img->paste($vignette, new Point(0, 0));

            // 4) Apply bottom gradient overlay (matches actual output dimensions)
            $gradientAbs = $this->overlayPath("gradient_{$finalW}x{$panelH}.png");
            $gradient = $imagine->open($gradientAbs);
            $img->paste($gradient, new Point(0, $finalH - $panelH));

            // Optional: grain/texture overlay (off by default)
            if ((bool)($this->style['grain'] ?? false)) {
                $grainAbs = $this->overlayPath("grain_{$finalW}x{$finalH}.png");
                if (file_exists($grainAbs)) {
                    $grain = $imagine->open($grainAbs);
                    $img->paste($grain, new Point(0, 0));
                }
            }

            // 5) Draw text
            $this->drawVerseAndReference($img, $verseText, $refText, $finalW, $finalH, $panelH);

            // 6) Save JPG
            $final = new VerseCardAsset();
            $final->session_id = $session->id;
            $final->kind = 'final';
            $final->state = 'ready';
            $final->disk = 'ephemeral';
            $final->expires_at = $session->expires_at;
            $final->pixabay_id = $selected->pixabay_id;
            $final->pixabay_user = $selected->pixabay_user;
            $final->pixabay_page_url = $selected->pixabay_page_url;
            $final->width = $finalW;
            $final->height = $finalH;
            $final->save();

            $finalPath = "verse-cards/{$session->id}/final/{$final->id}.jpg";
            $finalAbs  = Storage::disk('ephemeral')->path($finalPath);

            $finalDir = dirname($finalAbs);
            if (!is_dir($finalDir)) {
                mkdir($finalDir, 0775, true);
            }

            $quality = (int)($this->style['jpg_quality'] ?? self::JPG_QUALITY);
            $quality = max(60, min(95, $quality));

            $img->save($finalAbs, ['jpeg_quality' => $quality]);

            $final->path = $finalPath;
            $final->bytes = @filesize($finalAbs) ?: null;
            $final->save();

            $session->status = VerseCardSessionStatus::Ready->value;
            $session->save();
        } catch (ModelNotFoundException) {
            Log::info('RenderVerseCard (Imagine): missing session/asset', [
                'sessionId' => $this->sessionId,
                'selectedAssetId' => $this->selectedAssetId,
            ]);
            VerseCardSession::query()
                ->whereKey($this->sessionId)
                ->update(['status' => VerseCardSessionStatus::Choosing->value]);

        } catch (Throwable $e) {
            Log::error('RenderVerseCard (Imagine) failed', [
                'sessionId' => $this->sessionId,
                'selectedAssetId' => $this->selectedAssetId,
                'error' => $e->getMessage(),
            ]);

            VerseCardSession::query()
                ->whereKey($this->sessionId)
                ->update(['status' => VerseCardSessionStatus::Failed->value]);

            throw $e;
        }
    }

    private function getVerseText(VerseCardSession $session): string
    {
        return trim((string)($session->verse_text ?? 'For God so loved the world, that he gave his only Son...'));
    }

    private function getReferenceText(VerseCardSession $session): string
    {
        return trim((string)($session->verse_ref ?? 'John 3:16'));
    }

    private function drawVerseAndReference(\Imagine\Image\ImageInterface $img, string $verseText, string $refText, int $imgW, int $imgH, int $panelH): void
    {
        $fontVerse = $this->fontPath(self::FONT_VERSE);
        $fontRef   = $this->fontPath(self::FONT_REF);

        $drawer = $img->draw();
        $palette = new RGB();
        /** @var \Imagine\Imagick\Image $imagickImg */
        $imagickImg = $img;
        $imagick = $imagickImg->getImagick();

        $maxWidth = $imgW - (2 * self::PAD_X);

        // Scale text size based on image dimensions
        // Use the smaller dimension (height or width) as the basis for scaling
        $baseDimension = min($imgW, $imgH);
        $baselineSize = 1000; // baseline dimension for 52px max size
        
        // Fit verse to box by decreasing font size
        $maxSize = (int)($this->style['verse_max_size'] ?? max(20, (int)($baseDimension * 42 / $baselineSize)));
        $minSize = (int)($this->style['verse_min_size'] ?? max(10, (int)($baseDimension * 20 / $baselineSize)));

        // Gap between verse block and ref line
        $refGap  = 20;
        $refSize = (int)($this->style['ref_size'] ?? 0); // 0 = auto

        // Reserve space for ref when fitting verse height
        $roughRefReserve = (int)ceil($maxSize * 0.55 * 1.25) + $refGap;
        $availableH = max(80, $panelH - self::PAD_BOTTOM - $roughRefReserve);

        $fit = $this->fitWrappedTextGD($verseText, $fontVerse, $maxWidth, $availableH, $maxSize, $minSize);

        $lines      = $fit['lines'];
        $fontSize   = $fit['fontSize'];
        $lineHeight = $fit['lineHeight'];

        // Compute actual ref size based on fitted verse size
        if ($refSize <= 0) {
            $refSize = (int)max(22, floor($fontSize * 0.52));
        }
        $refLineHeight = (int)ceil($refSize * 1.25);

        // Total block height: verse lines + gap + ref line
        $verseBlockH = (count($lines) * $lineHeight) + ((count($lines) - 1) * self::LINE_GAP);
        $totalBlockH = $verseBlockH + $refGap + $refLineHeight;

        // Anchor block so its bottom edge sits PAD_BOTTOM above the image bottom
        $blockStartY = $imgH - self::PAD_BOTTOM - $totalBlockH;

        // Imagick driver: alpha 100 = fully opaque, 0 = fully transparent
        $shadow   = $palette->color([0, 0, 0], 100);      // fully opaque black
        $white    = $palette->color([255, 255, 255], 100); // fully opaque white
        $refWhite = $palette->color([255, 255, 255], 90);  // slightly transparent white

        $dx = (int)($this->style['shadow_dx'] ?? 2);
        $dy = (int)($this->style['shadow_dy'] ?? 3);

        $verseFont       = new \Imagine\Imagick\Font($imagick, $fontVerse, $fontSize, $white);
        $verseShadowFont = new \Imagine\Imagick\Font($imagick, $fontVerse, $fontSize, $shadow);

        $y = $blockStartY;

        foreach ($lines as $line) {
            // Center each line horizontally
            $lineW = $this->measureTextWidthGD($line, $fontVerse, $fontSize);
            $x = (int)floor(($imgW - $lineW) / 2);
            if ($x < 0) $x = 0; // precaution

            // shadow
            $drawer->text($line, $verseShadowFont, new Point($x + $dx, $y + $dy));
            // main
            $drawer->text($line, $verseFont, new Point($x, $y));

            $y += $lineHeight + self::LINE_GAP;
        }

        // Reference line — placed directly below verse block
        $refY = $blockStartY + $verseBlockH + $refGap;

        $refFont       = new \Imagine\Imagick\Font($imagick, $fontRef, $refSize, $refWhite);
        $refShadowFont = new \Imagine\Imagick\Font($imagick, $fontRef, $refSize, $shadow);

        // Center ref horizontally
        $refW = $this->measureTextWidthGD($refText, $fontRef, $refSize);
        $refX = (int)floor(($imgW - $refW) / 2);

        $drawer->text($refText, $refShadowFont, new Point($refX + $dx, $refY + $dy));
        $drawer->text($refText, $refFont, new Point($refX, $refY));
    }

    /**
     * Fit wrapped text in a box using GD font metrics (approx).
     * We use imagettfbbox for width; height uses size * 1.25 heuristic.
     */
    private function fitWrappedTextGD(
        string $text,
        string $ttfPath,
        int $maxWidth,
        int $maxHeight,
        int $maxSize,
        int $minSize
    ): array {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $maxSize = max($minSize, $maxSize);

        for ($size = $maxSize; $size >= $minSize; $size--) {
            $lines = $this->wrapTextGD($text, $ttfPath, $size, $maxWidth);
            $lineHeight = (int)ceil($size * 1.25);

            $totalH = (count($lines) * $lineHeight) + ((count($lines) - 1) * self::LINE_GAP);

            // verify widths
            $fitsWidth = true;
            foreach ($lines as $line) {
                if ($this->measureTextWidthGD($line, $ttfPath, $size) > $maxWidth) {
                    $fitsWidth = false;
                    break;
                }
            }

            if ($fitsWidth && $totalH <= $maxHeight) {
                return ['lines' => $lines, 'fontSize' => $size, 'lineHeight' => $lineHeight];
            }
        }

        $lines = $this->wrapTextGD($text, $ttfPath, $minSize, $maxWidth);
        return ['lines' => $lines, 'fontSize' => $minSize, 'lineHeight' => (int)ceil($minSize * 1.25)];
    }

    private function wrapTextGD(string $text, string $ttfPath, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = ($line === '') ? $word : ($line . ' ' . $word);

            if ($this->measureTextWidthGD($candidate, $ttfPath, $fontSize) <= $maxWidth) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
                $line = $word;
                continue;
            }

            // long token fallback: hard break roughly
            $lines[] = $this->hardBreakGD($word, $ttfPath, $fontSize, $maxWidth);
            $line = '';
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function hardBreakGD(string $word, string $ttfPath, int $fontSize, int $maxWidth): string
    {
        $buf = '';
        $out = '';

        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $candidate = $buf . $ch;
            if ($this->measureTextWidthGD($candidate, $ttfPath, $fontSize) <= $maxWidth) {
                $buf = $candidate;
                continue;
            }
            if ($buf === '') {
                $out .= $ch;
                continue;
            }
            // return first chunk; verses rarely hit this
            return $buf . '…';
        }

        return $buf ?: $word;
    }

    private function measureTextWidthGD(string $text, string $ttfPath, int $fontSize): int
    {
        // imagettfbbox returns an array of 8 coords
        $box = imagettfbbox($fontSize, 0, $ttfPath, $text);
        if (!is_array($box)) return 0;
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        return (int)(max($xs) - min($xs));
    }

    private function ensureOverlaysExist(int $width, int $height, int $panelH): void
    {
        $dir = storage_path('app/verse-card-overlays');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $gradient = $this->overlayPath("gradient_{$width}x{$panelH}.png");
        if (!file_exists($gradient)) {
            $this->generateBottomGradientPng($gradient, $width, $panelH);
        }

        $vignette = $this->overlayPath("vignette_{$width}x{$height}.png");
        if (!file_exists($vignette)) {
            $this->generateVignettePng($vignette, $width, $height);
        }
        
        $grain = $this->overlayPath("grain_{$width}x{$height}.png");
        if (!file_exists($grain)) $this->generateGrainPng($grain, $width, $height);
    }

    private function overlayPath(string $file): string
    {
        return storage_path('app/verse-card-overlays/' . $file);
    }

    /**
     * Generates a transparent->black bottom gradient PNG using raw GD.
     * (Done once, then reused. Fast at runtime.)
     */
    private function generateBottomGradientPng(string $path, int $w, int $h): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagealphablending($im, false);

        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $transparent);

        // alpha: 127 fully transparent -> 0 fully opaque
        // We want top transparent, bottom ~ 62% black
        imagealphablending($im, true);

        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            // final opacity target: about 0.62 black at bottom
            $opacity = (int)round(127 - (127 * 0.62 * $t)); // decreases towards bottom (less alpha => more opaque)
            $opacity = max(0, min(127, $opacity));
            $col = imagecolorallocatealpha($im, 0, 0, 0, $opacity);
            imageline($im, 0, $y, $w, $y, $col);
        }

        imagepng($im, $path);
        imagedestroy($im);
    }

    /**
     * Generates a vignette PNG (transparent center, darker edges).
     * Cheap, looks good, and reusable.
     */
    private function generateVignettePng(string $path, int $w, int $h): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagealphablending($im, false);

        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $transparent);

        imagealphablending($im, true);

        $cx = $w / 2.0;
        $cy = $h / 2.0;
        $maxR = sqrt(($cx * $cx) + ($cy * $cy));

        // tune: edge darkness ~ 25-35%
        $edgeStrength = 0.30;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = $x - $cx;
                $dy = $y - $cy;
                $r = sqrt($dx * $dx + $dy * $dy) / max(1e-6, $maxR);

                // smoothstep-ish curve: no dark in center, grows near edges
                $v = max(0.0, min(1.0, ($r - 0.35) / 0.65));
                $v = $v * $v * (3 - 2 * $v); // smoothstep

                $opacity = (int)round(127 - (127 * ($edgeStrength * $v))); // less alpha => more opaque
                $opacity = max(0, min(127, $opacity));
                $col = imagecolorallocatealpha($im, 0, 0, 0, $opacity);
                imagesetpixel($im, $x, $y, $col);
            }
        }

        imagepng($im, $path);
        imagedestroy($im);
    }

    /**
     * Generates a grain/texture overlay PNG with semi-transparent noise.
     * Creates a subtle film grain effect that can be applied over the final image.
     */
    private function generateGrainPng(string $path, int $w, int $h): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagealphablending($im, false);

        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $transparent);

        imagealphablending($im, true);

        // Grain intensity: controls opacity of the noise (0-127, where 127 is fully transparent)
        $grainIntensity = 10; // ~78% transparent, ~22% opaque grain

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                // Generate random noise value (0-255)
                $noise = mt_rand(0, 255);

                // Map noise to opacity: higher noise = more opaque grain
                // Scale noise (0-255) to alpha range (127 fully transparent to 0 fully opaque)
                $opacity = (int)round(127 - (($noise / 255) * $grainIntensity));
                $opacity = max(0, min(127, $opacity));

                // Create grain pixel with white color and calculated opacity
                $col = imagecolorallocatealpha($im, 255, 255, 255, $opacity);
                imagesetpixel($im, $x, $y, $col);
            }
        }

        imagepng($im, $path);
        imagedestroy($im);
    }

    private function fontPath(string $relative): string
    {
        $relative = ltrim($relative, '/');

        $p1 = storage_path('app/' . $relative);
        if (file_exists($p1)) return $p1;

        $p2 = resource_path($relative);
        if (file_exists($p2)) return $p2;

        return $relative; // allow absolute container path
    }

    /**
     * Download full-size remote image for rendering.
     *
     * @param VerseCardAsset $selected
     * @param VerseCardSession $session
     * @param string $disk
     * @param PixabayImageStorage $imageStorage
     * @return string|null
     */
    private function downloadRemoteImage(VerseCardAsset $selected, VerseCardSession $session, string $disk, PixabayImageStorage $imageStorage): ?string
    {
        $remoteUrl = $selected->remote_url;
        if (!$remoteUrl) {
            Log::error('Asset missing remote_url', ['assetId' => $selected->id]);
            return null;
        }

        // Determine storage path based on Pixabay ID
        if (!$selected->pixabay_id) {
            Log::error('Asset missing pixabay_id, cannot store in common folder', ['assetId' => $selected->id]);
            return null;
        }

        $path = $imageStorage->getImagePath($selected->pixabay_id, 'full');

        // Download if missing (storage service handles locking and existence check)
        if (!$imageStorage->downloadIfMissing($remoteUrl, $path, $disk)) {
            Log::error('Failed to download remote image', ['assetId' => $selected->id]);
            return null;
        }

        Log::info('Remote image downloaded', [
            'assetId' => $selected->id,
            'path' => $path,
        ]);

        return $path;
    }

    /**
     * Select the closest preset dimensions based on source aspect ratio.
     * Finds the preset that minimizes the difference in aspect ratio.
     *
     * @param float $srcAspect Source image aspect ratio (width / height)
     * @return array [width, height]
     */
    private function selectClosestDimensions(float $srcAspect): array
    {
        $closest = self::PRESET_DIMENSIONS[0];
        $minDiff = PHP_FLOAT_MAX;

        foreach (self::PRESET_DIMENSIONS as $preset) {
            $presetAspect = $preset[0] / max(1, $preset[1]);
            $diff = abs($srcAspect - $presetAspect);

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $preset;
            }
        }

        return $closest;
    }
}