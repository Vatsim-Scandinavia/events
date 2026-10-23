{{-- The plain error page, for when the frontend itself can't render. It deliberately uses no
     Vite assets or app layout, only inline styles carrying the CSP nonce. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem;
               font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: Canvas; color: CanvasText; }
        main { max-width: 32rem; text-align: center; }
        .status { font-size: 0.875rem; opacity: 0.6; letter-spacing: 0.05em; }
        h1 { margin: 0.5rem 0; font-size: 1.75rem; }
        p { opacity: 0.75; line-height: 1.5; }
        a { color: inherit; }
    </style>
</head>
<body>
    <main>
        <div class="status">{{ $exception->getStatusCode() }}</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <p><a href="{{ url('/') }}">Back to {{ config('app.name') }}</a></p>
    </main>
</body>
</html>
