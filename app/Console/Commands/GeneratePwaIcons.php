<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates every PWA / favicon asset from a single square source image.
 *
 * Uses GD (bundled with PHP, no extra dependency) so it runs identically on the
 * VPS. Output goes to public/icons/ and public/favicon.ico and is committed —
 * these are hand-generated static assets, not Vite output, so they do not
 * participate in the public/build deploy conflicts.
 *
 *   php artisan pwa:icons                      # uses the default source
 *   php artisan pwa:icons --source=logo.png    # uses your own square logo
 */
class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons
                            {--source= : Square source PNG (defaults to resources/pwa/icon-source.png)}
                            {--bg=#015083 : Background for maskable and iOS icons}';

    protected $description = 'Generate PWA app icons and favicons from a square source image';

    /** Maskable icons must keep their content inside the centre 80% safe zone. */
    private const MASKABLE_SCALE = 0.66;

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('The GD extension is required but not loaded.');

            return self::FAILURE;
        }

        $source = $this->resolveSource();

        if ($source === null) {
            return self::FAILURE;
        }

        $this->info('Source: ' . $source);

        $src = @imagecreatefromstring((string) file_get_contents($source));

        if ($src === false) {
            $this->error('Could not read the source image. It must be a PNG, JPEG or GIF.');

            return self::FAILURE;
        }

        [$w, $h] = [imagesx($src), imagesy($src)];

        if ($w !== $h) {
            $this->warn("Source is {$w}x{$h}, not square. It will be centre-cropped.");
            $src = $this->cropSquare($src, $w, $h);
        }

        $dir = public_path('icons');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bg = $this->parseColor((string) $this->option('bg'));

        // Transparent, full-bleed icons.
        $this->write($src, $dir . '/icon-192.png', 192);
        $this->write($src, $dir . '/icon-512.png', 512);
        $this->write($src, $dir . '/favicon-32.png', 32);
        $this->write($src, $dir . '/favicon-16.png', 16);

        // Maskable: inset on a solid background so Android can crop to any shape.
        $this->write($src, $dir . '/icon-maskable-192.png', 192, $bg, self::MASKABLE_SCALE);
        $this->write($src, $dir . '/icon-maskable-512.png', 512, $bg, self::MASKABLE_SCALE);

        // iOS ignores transparency and renders it black, so this one must be opaque.
        $this->write($src, $dir . '/apple-touch-icon.png', 180, $bg, 0.86);

        $this->writeIco($src, public_path('favicon.ico'), $bg);

        imagedestroy($src);

        $this->newLine();
        $this->info('Done. Icons written to public/icons/ and public/favicon.ico');

        return self::SUCCESS;
    }

    /** --source, else the user's logo slot, else the shipped placeholder. */
    private function resolveSource(): ?string
    {
        $candidates = array_filter([
            $this->option('source'),
            resource_path('pwa/icon-source.png'),
            public_path('images/cameroonflag.png'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if ($candidate === public_path('images/cameroonflag.png')) {
                    $this->warn('Using the placeholder flag image. Drop your logo at '
                        . 'resources/pwa/icon-source.png and re-run to replace it.');
                }

                return $candidate;
            }
        }

        $this->error('No source image found. Pass --source or add resources/pwa/icon-source.png');

        return null;
    }

    /** @return \GdImage */
    private function cropSquare(\GdImage $src, int $w, int $h): \GdImage
    {
        $size = min($w, $h);
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopy($out, $src, 0, 0, intdiv($w - $size, 2), intdiv($h - $size, 2), $size, $size);
        imagedestroy($src);

        return $out;
    }

    /**
     * Resize onto a canvas of $size, optionally over a solid background and
     * scaled down to leave padding (used for maskable and iOS icons).
     *
     * @param  array{0:int,1:int,2:int}|null  $bg
     */
    private function write(\GdImage $src, string $path, int $size, ?array $bg = null, float $scale = 1.0): void
    {
        $canvas = imagecreatetruecolor($size, $size);

        if ($bg === null) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagealphablending($canvas, true);
        } else {
            imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, ...$bg));
        }

        $inner = (int) round($size * $scale);
        $offset = intdiv($size - $inner, 2);

        imagecopyresampled($canvas, $src, $offset, $offset, 0, 0, $inner, $inner, imagesx($src), imagesy($src));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagepng($canvas, $path, 9);
        imagedestroy($canvas);

        $this->line('  ' . str_pad(basename($path), 26) . $size . 'x' . $size);
    }

    /**
     * GD cannot write .ico, so wrap a 32x32 PNG in a 22-byte ICO header.
     * Every browser since IE11 accepts PNG-in-ICO.
     *
     * @param  array{0:int,1:int,2:int}  $bg
     */
    private function writeIco(\GdImage $src, string $path, array $bg): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ico');
        $this->write($src, $tmp, 32, $bg);
        $png = (string) file_get_contents($tmp);
        @unlink($tmp);

        $header = pack('vvv', 0, 1, 1)
            . pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22);

        file_put_contents($path, $header . $png);
        $this->line('  ' . str_pad('favicon.ico', 26) . '32x32 (PNG-in-ICO)');
    }

    /** @return array{0:int,1:int,2:int} */
    private function parseColor(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '015083';
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
