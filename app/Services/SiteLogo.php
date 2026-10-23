<?php

namespace App\Services;

use enshrined\svgSanitize\Sanitizer;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores the vACC's logo on the public disk, along with the favicons made from it.
 *
 * Images are redrawn with GD, so only their pixels are kept. SVGs are kept as vectors
 * but cleaned first, removing scripts, event handlers and links to other sites; the
 * cleaned SVG doubles as the favicon, since GD can't draw SVG.
 */
final class SiteLogo
{
    private const string DISK = 'public';

    private const string DIRECTORY = 'branding';

    private const int MAX_HEIGHT = 256;

    private const int ICON_SIZE = 32;

    private const int TOUCH_ICON_SIZE = 180;

    /**
     * A logo has no need to come anywhere near this (see GdImages::read).
     */
    private const int MAX_PIXELS = 16_000_000;

    /**
     * Store an upload and its favicons, returning their paths on the public disk.
     *
     * @return array{logo: string, icon: string, touch: ?string, type: string}
     *
     * @throws ValidationException when the file can't be read as a logo
     */
    public function store(UploadedFile $file): array
    {
        return $this->isSvg($file) ? $this->storeSvg($file) : $this->storeImage($file);
    }

    /**
     * @param  array{logo: string, icon: string, touch: ?string, type: string}|null  $logo
     */
    public function delete(?array $logo): void
    {
        if ($logo !== null) {
            Storage::disk(self::DISK)->delete(array_unique(array_filter([$logo['logo'], $logo['icon'], $logo['touch']])));
        }
    }

    private function isSvg(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), ['image/svg+xml', 'image/svg'], true)
            || strtolower($file->getClientOriginalExtension()) === 'svg';
    }

    /**
     * @return array{logo: string, icon: string, touch: ?string, type: string}
     */
    private function storeSvg(UploadedFile $file): array
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->removeXMLTag(true);

        $clean = $sanitizer->sanitize((string) file_get_contents($file->getRealPath()));

        if (! is_string($clean) || ! preg_match('/^\s*<svg[\s>]/i', $clean)) {
            throw ValidationException::withMessages(['logo' => 'That SVG couldn’t be read. Try exporting it again, or upload a PNG.']);
        }

        $path = $this->path('svg');
        Storage::disk(self::DISK)->put($path, $clean);

        return ['logo' => $path, 'icon' => $path, 'touch' => null, 'type' => 'image/svg+xml'];
    }

    /**
     * @return array{logo: string, icon: string, touch: ?string, type: string}
     */
    private function storeImage(UploadedFile $file): array
    {
        $image = GdImages::read(
            $file,
            self::MAX_PIXELS,
            'logo',
            'That image is too large. A logo around 512 pixels tall is plenty.',
            'That image couldn’t be read. Try saving it as a PNG.',
        );

        $paths = [
            'logo' => $this->put($this->scaledToHeight($image), 'webp'),
            'icon' => $this->put($this->square($image, self::ICON_SIZE), 'png'),
            'touch' => $this->put($this->square($image, self::TOUCH_ICON_SIZE), 'png'),
        ];

        return [...$paths, 'type' => 'image/png'];
    }

    private function scaledToHeight(GdImage $image): GdImage
    {
        if (imagesy($image) <= self::MAX_HEIGHT) {
            return $image;
        }

        return imagescale($image, (int) round(imagesx($image) * self::MAX_HEIGHT / imagesy($image)), self::MAX_HEIGHT, IMG_BICUBIC) ?: $image;
    }

    /**
     * The whole logo fitted into a transparent square, as icons must be square.
     */
    private function square(GdImage $image, int $size): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = $size / max($width, $height);
        $fittedWidth = max(1, (int) round($width * $scale));
        $fittedHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, intdiv($size - $fittedWidth, 2), intdiv($size - $fittedHeight, 2), 0, 0, $fittedWidth, $fittedHeight, $width, $height);

        return $canvas;
    }

    private function put(GdImage $image, string $format): string
    {
        $path = $this->path($format);
        Storage::disk(self::DISK)->put($path, GdImages::encode($image, $format, $format === 'webp' ? 90 : 9));

        return $path;
    }

    private function path(string $extension): string
    {
        return self::DIRECTORY.'/'.Str::uuid().'.'.$extension;
    }
}
