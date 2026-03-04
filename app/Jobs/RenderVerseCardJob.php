<?php

namespace SzentirasHu\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
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

    private const OUT_W = 1600;
    private const OUT_H = 1200;

    // Bottom text-safe panel height
    private const PANEL_H = 520;

    private const PAD_X      = 90;
    private const PAD_BOTTOM = 80;  // distance from image bottom to bottom of text block
    private const LINE_GAP   = 10;

    private const JPG_QUALITY = 88;

    private const FONT_VERSE = 'assets/fonts/NotoSerif-Regular.ttf';
    private const FONT_REF   = 'assets/fonts/NotoSans-Regular.ttf';

    public function handle(): void
    {
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
            if (!$selected->path || !Storage::disk($disk)->exists($selected->path)) {
                throw new \RuntimeException("Selected image missing: {$disk}:{$selected->path}");
            }

            $verseText = $this->getVerseText($session);
            $refText   = $this->getReferenceText($session);

            $session->status = VerseCardSessionStatus::Rendering->value;
            $session->save();

            // Ensure overlays exist (generated once)
            $this->ensureOverlaysExist();

            $imagine = new Imagine();

            $sourceAbs = Storage::disk($disk)->path($selected->path);

            // 1) Load
            $img = $imagine->open($sourceAbs);

            // 2) Cover-crop to OUT_W x OUT_H:
            // Scale so the image covers the target box, then crop to exact size.
            $srcSize = $img->getSize();
            $scaleW  = self::OUT_W / $srcSize->getWidth();
            $scaleH  = self::OUT_H / $srcSize->getHeight();
            $scale   = max($scaleW, $scaleH);
            $scaledW = (int)ceil($srcSize->getWidth() * $scale);
            $scaledH = (int)ceil($srcSize->getHeight() * $scale);
            $img = $img->resize(new Box($scaledW, $scaledH));
            // Crop to exact target size from center
            $cropX = (int)floor(($scaledW - self::OUT_W) / 2);
            $cropY = (int)floor(($scaledH - self::OUT_H) / 2);
            $img = $img->crop(new Point($cropX, $cropY), new Box(self::OUT_W, self::OUT_H));

            // 3) Apply vignette overlay
            $vignetteAbs = $this->overlayPath("vignette_" . self::OUT_W . "x" . self::OUT_H . ".png");
            $vignette = $imagine->open($vignetteAbs);
            $img->paste($vignette, new Point(0, 0));

            // 4) Apply bottom gradient overlay
            $gradientAbs = $this->overlayPath("gradient_" . self::OUT_W . "x" . self::PANEL_H . ".png");
            $gradient = $imagine->open($gradientAbs);
            $img->paste($gradient, new Point(0, self::OUT_H - self::PANEL_H));

            // Optional: grain/texture overlay (off by default)
            if ((bool)($this->style['grain'] ?? false)) {
                $grainAbs = $this->overlayPath("grain_" . self::OUT_W . "x" . self::OUT_H . ".png");
                if (file_exists($grainAbs)) {
                    $grain = $imagine->open($grainAbs);
                    $img->paste($grain, new Point(0, 0));
                }
            }

            // 5) Draw text
            $this->drawVerseAndReference($img, $verseText, $refText);

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

    private function drawVerseAndReference(\Imagine\Image\ImageInterface $img, string $verseText, string $refText): void
    {
        $fontVerse = $this->fontPath(self::FONT_VERSE);
        $fontRef   = $this->fontPath(self::FONT_REF);

        $drawer = $img->draw();
        $palette = new RGB();
        /** @var \Imagine\Imagick\Image $imagickImg */
        $imagickImg = $img;
        $imagick = $imagickImg->getImagick();

        $maxWidth = self::OUT_W - (2 * self::PAD_X);

        // Fit verse to box by decreasing font size
        $maxSize = (int)($this->style['verse_max_size'] ?? 52);
        $minSize = (int)($this->style['verse_min_size'] ?? 24);

        // Gap between verse block and ref line
        $refGap  = 20;
        $refSize = (int)($this->style['ref_size'] ?? 0); // 0 = auto

        // Reserve space for ref when fitting verse height
        $roughRefReserve = (int)ceil($maxSize * 0.55 * 1.25) + $refGap;
        $availableH = max(80, self::PANEL_H - self::PAD_BOTTOM - $roughRefReserve);

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
        $blockStartY = self::OUT_H - self::PAD_BOTTOM - $totalBlockH;

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
            $x = (int)floor((self::OUT_W - $lineW) / 2);

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
        $refX = (int)floor((self::OUT_W - $refW) / 2);

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

    private function ensureOverlaysExist(): void
    {
        $dir = storage_path('app/verse-card-overlays');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $gradient = $this->overlayPath("gradient_" . self::OUT_W . "x" . self::PANEL_H . ".png");
        if (!file_exists($gradient)) {
            $this->generateBottomGradientPng($gradient, self::OUT_W, self::PANEL_H);
        }

        $vignette = $this->overlayPath("vignette_" . self::OUT_W . "x" . self::OUT_H . ".png");
        if (!file_exists($vignette)) {
            $this->generateVignettePng($vignette, self::OUT_W, self::OUT_H);
        }

        // Optional grain (only if you want it):
        // $grain = $this->overlayPath("grain_" . self::OUT_W . "x" . self::OUT_H . ".png");
        // if (!file_exists($grain)) $this->generateGrainPng($grain, self::OUT_W, self::OUT_H);
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

    private function fontPath(string $relative): string
    {
        $relative = ltrim($relative, '/');

        $p1 = storage_path('app/' . $relative);
        if (file_exists($p1)) return $p1;

        $p2 = resource_path($relative);
        if (file_exists($p2)) return $p2;

        return $relative; // allow absolute container path
    }
}