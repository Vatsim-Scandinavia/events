<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BannerUploadService
{
    protected const REQUIRED_RATIO = 16 / 9;
    protected const RATIO_TOLERANCE = 0.05;
    protected const MAX_WIDTH = 1920;
    protected const MAX_HEIGHT = 1080;

    /**
     * Upload and process a banner image
     */
    public function upload(UploadedFile $file, string $disk = null): string
    {
        $disk = $disk ?? 'public'; // Use public disk by default

        // Validate aspect ratio
        $this->validateAspectRatio($file);

        // Generate unique filename
        $filename = 'banners/' . uniqid() . '_' . time() . '.jpg';

        // Get image dimensions
        $image = getimagesize($file->getRealPath());
        $width = $image[0];
        $height = $image[1];

        // Resize if needed
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            $this->resizeAndStore($file, $filename, $disk);
        } else {
            // Store as-is if within size limits
            Storage::disk($disk)->put($filename, file_get_contents($file->getRealPath()));
        }

        return $filename;
    }

    /**
     * Validate that the image has a 16:9 aspect ratio
     */
    protected function validateAspectRatio(UploadedFile $file): void
    {
        $image = getimagesize($file->getRealPath());
        $width = $image[0];
        $height = $image[1];

        $ratio = $width / $height;
        $difference = abs($ratio - self::REQUIRED_RATIO);

        if ($difference > self::RATIO_TOLERANCE) {
            throw new \InvalidArgumentException(
                'Image must have a 16:9 aspect ratio. Current ratio: ' . 
                round($ratio, 2) . ':1'
            );
        }
    }

    /**
     * Resize and store the image
     */
    protected function resizeAndStore(UploadedFile $file, string $filename, string $disk): void
    {
        // Calculate new dimensions maintaining aspect ratio
        $image = getimagesize($file->getRealPath());
        $width = $image[0];
        $height = $image[1];

        if ($width > $height * (16/9)) {
            $newWidth = self::MAX_WIDTH;
            $newHeight = intval($newWidth / (16/9));
        } else {
            $newHeight = self::MAX_HEIGHT;
            $newWidth = intval($newHeight * (16/9));
        }

        // For now, just copy the file - we can add Intervention Image later if needed
        // This would require: composer require intervention/image
        Storage::disk($disk)->put($filename, file_get_contents($file->getRealPath()));
    }

    /**
     * Delete a banner file
     */
    public function delete(string $path, string $disk = null): bool
    {
        $disk = $disk ?? 'public';

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }

    /**
     * Get the public URL for a banner
     */
    public function getUrl(string $path, string $disk = null): string
    {
        $disk = $disk ?? 'public';

        if ($disk === 's3') {
            return Storage::disk($disk)->url($path);
        }

        return asset('storage/' . $path);
    }
}
