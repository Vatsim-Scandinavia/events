<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side defences on every page: a Content Security Policy that only runs scripts
 * we serve (tagged with Vite's nonce), no framing, and no MIME sniffing.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vite adds this nonce to the script tags it renders, including the React refresh preamble.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        // While `npm run dev` runs, scripts, styles, fonts and hot reloads come from the Vite server.
        $vite = Vite::isRunningHot() ? $this->source(trim((string) file_get_contents(Vite::hotFile()))) : null;
        $viteSocket = $vite !== null ? preg_replace('/^http/', 'ws', $vite) : null;

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", $vite],
            // Inline styles come from the font loader and from components that position things.
            'style-src' => ["'self'", "'unsafe-inline'", $vite],
            // Banners are served from the public disk, which may live on another origin.
            'img-src' => ["'self'", 'data:', 'blob:', $this->source(Storage::disk('public')->url(''))],
            'font-src' => ["'self'", 'data:', $vite],
            'connect-src' => ["'self'", $vite, $viteSocket],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_unique(array_filter($sources))))
            ->implode('; ');
    }

    /**
     * The origin of a URL as a CSP source. CSP can't name IPv6 hosts like [::1], which
     * Vite listens on by default, so those fall back to their scheme.
     */
    private function source(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (str_starts_with($parts['host'], '[')) {
            return $parts['scheme'].':';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
