<!DOCTYPE html>
<html class="dark" style="color-scheme: dark" data-theme="{{ app(\App\Services\VaccIdentity::class)->theme()->value }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($favicon = app(\App\Services\VaccIdentity::class)->favicon())
        <link rel="icon" href="{{ $favicon['icon'] }}" type="{{ $favicon['type'] }}">
        @if ($favicon['touch'])
            <link rel="apple-touch-icon" href="{{ $favicon['touch'] }}">
        @endif
        @fonts
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        <x-inertia::head />
    </head>
    <body>
        <x-inertia::app id="event_hub"/>
    </body>
</html>
