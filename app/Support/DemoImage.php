<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Generates soft gradient-mesh WebP placeholders for demo content (GD only).
 * Real photos replace these via the admin panel.
 */
final class DemoImage
{
    /**
     * Palettes of three hex colours: [base, glow A, glow B].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    public const PALETTES = [
        ['#4338ca', '#a78bfa', '#f0abfc'],
        ['#0f766e', '#5eead4', '#a5b4fc'],
        ['#be185d', '#fda4af', '#c4b5fd'],
        ['#1d4ed8', '#7dd3fc', '#c084fc'],
        ['#b45309', '#fcd34d', '#fb7185'],
        ['#6d28d9', '#f0abfc', '#67e8f9'],
        ['#047857', '#86efac', '#93c5fd'],
        ['#9f1239', '#fdba74', '#e879f9'],
    ];

    /**
     * Create (once) a WebP on the public disk and return its relative path.
     */
    public static function make(string $path, int $width, int $height, int $palette = 0, ?string $text = null, float $textScale = 0.34): string
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path) || ! function_exists('imagewebp')) {
            return $path;
        }

        [$base, $glowA, $glowB] = self::PALETTES[$palette % count(self::PALETTES)];

        // Paint at 1/8 scale, blur, then scale up: smooth mesh look and fast.
        $sw = max(8, intdiv($width, 8));
        $sh = max(8, intdiv($height, 8));
        $small = imagecreatetruecolor($sw, $sh);
        imagealphablending($small, true);

        [$br, $bg, $bb] = self::rgb($base);
        [$ar, $ag, $ab] = self::rgb($glowA);

        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $t = ($x / $sw * 0.45) + ($y / $sh * 0.55);
                imagesetpixel($small, $x, $y, imagecolorallocate(
                    $small,
                    (int) ($br + ($ar - $br) * $t * 0.55),
                    (int) ($bg + ($ag - $bg) * $t * 0.55),
                    (int) ($bb + ($ab - $bb) * $t * 0.55),
                ));
            }
        }

        self::glow($small, $glowA, (int) ($sw * 0.85), (int) ($sh * 0.18), (int) ($sw * 0.55), 60);
        self::glow($small, $glowB, (int) ($sw * 0.1), (int) ($sh * 0.85), (int) ($sw * 0.6), 70);
        self::glow($small, '#ffffff', (int) ($sw * 0.5), (int) ($sh * 0.45), (int) ($sw * 0.3), 100);

        for ($i = 0; $i < 4; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $image = imagescale($small, $width, $height, IMG_BICUBIC_FIXED);

        if ($text !== null && $text !== '') {
            self::centerText($image, $text, (int) ($height * $textScale), $width, $height);
        }

        ob_start();
        imagewebp($image, null, 80);
        $binary = (string) ob_get_clean();

        $disk->put($path, $binary, 'public');

        return $path;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function glow(\GdImage $image, string $hex, int $cx, int $cy, int $radius, int $alpha): void
    {
        [$r, $g, $b] = self::rgb($hex);

        for ($step = 0; $step < 6; $step++) {
            $size = (int) ($radius * 2 * (1 - $step * 0.14));
            $transparency = 127 - (int) ($alpha * 0.12);
            imagefilledellipse($image, $cx, $cy, $size, $size, imagecolorallocatealpha($image, $r, $g, $b, $transparency));
        }
    }

    private static function centerText(\GdImage $image, string $text, int $size, int $width, int $height): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! is_file($font) || ! function_exists('imagettftext')) {
            return;
        }

        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);

        $x = (int) (($width - $textWidth) / 2 - $box[0]);
        $y = (int) (($height + $textHeight) / 2);

        // Soft shadow, then white text.
        imagettftext($image, $size, 0, $x + 3, $y + 4, imagecolorallocatealpha($image, 0, 0, 0, 100), $font, $text);
        imagettftext($image, $size, 0, $x, $y, imagecolorallocatealpha($image, 255, 255, 255, 8), $font, $text);
    }
}
