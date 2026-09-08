<?php

namespace App\Http\Controllers;

use App\Actions\RenderEventMarkdown;
use App\Http\Requests\EventMarkdownPreviewRequest;
use Illuminate\Http\JsonResponse;

class EventMarkdownPreviewController extends Controller
{
    public function __invoke(EventMarkdownPreviewRequest $request, RenderEventMarkdown $markdown): JsonResponse
    {
        return response()->json([
            'html' => $markdown->handle($request->validated('markdown') ?? ''),
        ]);
    }
}
