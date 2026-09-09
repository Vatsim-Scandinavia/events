<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventBannerController extends Controller
{
    public function __invoke(Request $request, Event $event): StreamedResponse
    {
        abort_unless($event->isPubliclyVisible() || ($request->user()?->can('view', $event) ?? false), 404);
        abort_if($event->banner_path === null || ! Storage::disk('local')->exists($event->banner_path), 404);

        return Storage::disk('local')->response($event->banner_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
