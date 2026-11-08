{{-- Read by sites that unfurl links, such as Discord. Pages without their own preview describe the Hub. --}}
@php($preview ??= app(\App\Services\LinkPreview::class)->site(request()->url()))
<meta name="description" content="{{ $preview['description'] }}">
<meta property="og:site_name" content="{{ $preview['siteName'] }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $preview['title'] }}">
<meta property="og:description" content="{{ $preview['description'] }}">
<meta property="og:url" content="{{ $preview['url'] }}">
@if ($preview['image'])
    <meta property="og:image" content="{{ $preview['image'] }}">
@endif
<meta name="twitter:card" content="{{ $preview['card'] }}">
