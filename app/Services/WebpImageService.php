<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class WebpImageService
{
    public function __construct(
        protected string $disk = 'public',
        protected int $maxWidth = 1600,
        protected int $quality = 82,
    ) {}

    /**
     * Convert a stored JPEG/PNG into WebP and return the new relative path.
     * Returns the original path untouched when conversion is not possible.
     */
    public function convert(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return $path;
        }

        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return $path;
        }

        $storage = Storage::disk($this->disk);

        if (! $storage->exists($path)) {
            return $path;
        }

        $image = @imagecreatefromstring($storage->get($path));

        if ($image === false) {
            return $path;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        if (imagesx($image) > $this->maxWidth) {
            $scaled = imagescale($image, $this->maxWidth);

            if ($scaled !== false) {
                $image = $scaled;
            }
        }

        $target = substr($path, 0, -strlen($extension)).'webp';

        ob_start();
        $written = imagewebp($image, null, $this->quality);
        $binary = (string) ob_get_clean();

        if (! $written || $binary === '') {
            return $path;
        }

        $storage->put($target, $binary, 'public');
        $storage->delete($path);

        return $target;
    }
}
