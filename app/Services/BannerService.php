<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BannerService
{
    public function upload(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->guessExtension();
        return $file->storeAs('banners', $filename, 'public');
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
