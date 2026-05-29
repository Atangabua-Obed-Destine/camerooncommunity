<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Resize uploaded marketplace images:
 *   - main image  → JPEG, max 1600px wide, ~80% quality
 *   - thumbnail   → JPEG, 400px square cover-fit, ~75% quality
 *
 * Both written to the same 'public' disk next to the original. The original
 * upload is replaced by the optimized main image to save space.
 *
 * Returns an array: ['path' => string, 'thumb' => string, 'w' => int, 'h' => int].
 */
class MarketplaceImageProcessor
{
    public const MAIN_MAX_WIDTH  = 1600;
    public const MAIN_QUALITY    = 80;
    public const THUMB_SIZE      = 400;
    public const THUMB_QUALITY   = 75;

    /**
     * @param  string  $path  storage-relative path on the public disk
     * @return array{path:string, thumb:string, w:int, h:int}
     */
    public static function process(string $path): array
    {
        $disk = Storage::disk('public');
        $abs  = $disk->path($path);

        // Out paths: same dir, suffixed names.
        $dir   = trim(str_replace('\\', '/', dirname($path)), '/');
        $base  = pathinfo($path, PATHINFO_FILENAME);
        $main  = ($dir ? $dir . '/' : '') . $base . '.jpg';
        $thumb = ($dir ? $dir . '/' : '') . $base . '_thumb.jpg';

        try {
            // --- Main (constrained, keep aspect) ---
            $img = Image::read($abs);
            $img->scaleDown(width: self::MAIN_MAX_WIDTH); // never upscales
            $disk->put($main, (string) $img->toJpeg(quality: self::MAIN_QUALITY));
            $w = $img->width();
            $h = $img->height();

            // --- Thumbnail (square cover-fit) ---
            $thumbImg = Image::read($abs)->cover(self::THUMB_SIZE, self::THUMB_SIZE);
            $disk->put($thumb, (string) $thumbImg->toJpeg(quality: self::THUMB_QUALITY));

            // Drop the original if we created a different file for the main.
            if ($main !== $path && $disk->exists($path)) {
                $disk->delete($path);
            }

            return ['path' => $main, 'thumb' => $thumb, 'w' => $w, 'h' => $h];
        } catch (\Throwable $e) {
            Log::warning('Image processing failed; keeping original.', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            [$w, $h] = @getimagesize($abs) ?: [0, 0];
            return ['path' => $path, 'thumb' => $path, 'w' => (int) $w, 'h' => (int) $h];
        }
    }
}
