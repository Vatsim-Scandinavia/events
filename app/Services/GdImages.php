<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Reading uploads into GD and writing them back out, shared by banners and logos.
 */
final class GdImages
{
    /**
     * Decode an upload as a true-colour image. Decoding takes about four bytes per pixel,
     * so a small file claiming huge dimensions is refused before it can exhaust memory.
     *
     * @throws ValidationException under $field, with $tooLarge or $unreadable
     */
    public static function read(UploadedFile $file, int $maxPixels, string $field, string $tooLarge, string $unreadable): GdImage
    {
        $size = @getimagesize($file->getRealPath());

        if ($size !== false && $maxPixels < $size[0] * $size[1]) {
            throw ValidationException::withMessages([$field => $tooLarge]);
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => $unreadable]);
        }

        imagepalettetotruecolor($image);

        return $image;
    }

    /**
     * The image as WebP (quality 0–100) or PNG (compression 0–9), keeping transparency.
     */
    public static function encode(GdImage $image, string $format, int $quality): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $format === 'webp' ? imagewebp($image, null, $quality) : imagepng($image, null, $quality);

        return (string) ob_get_clean();
    }
}
