<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploaded event banners on the public disk. Every banner is converted to
 * WebP, whatever it was uploaded as, and scaled down when it is wider than needed.
 */
final class EventBanners
{
    private const string DISK = 'public';

    private const int MAX_WIDTH = 2400;

    private const int QUALITY = 82;

    /**
     * 25 megapixels is a 6000×4000 photo, roughly 100 MB once decoded.
     */
    private const int MAX_PIXELS = 25_000_000;

    /**
     * Convert and store an upload, returning its path on the public disk.
     *
     * @throws ValidationException when the file can't be read as an image or is too large to decode
     */
    public function store(UploadedFile $file): string
    {
        $image = GdImages::read(
            $file,
            self::MAX_PIXELS,
            'banner',
            'That image is too large. Use one under 25 megapixels, like 6000×4000.',
            'That image couldn’t be read. Try saving it as a JPG or PNG.',
        );

        $path = 'banners/'.Str::uuid().'.webp';
        Storage::disk(self::DISK)->put($path, GdImages::encode($this->scaled($image), 'webp', self::QUALITY));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path !== null ? Storage::disk(self::DISK)->url($path) : null;
    }

    private function scaled(GdImage $image): GdImage
    {
        if (imagesx($image) <= self::MAX_WIDTH) {
            return $image;
        }

        return imagescale($image, self::MAX_WIDTH, -1, IMG_BICUBIC) ?: $image;
    }
}
