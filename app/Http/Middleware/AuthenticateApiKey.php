<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

/**
 * Middleware to authenticate requests using an API key.
 */

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requireWrite = null): Response
    {
        $apiKey = ApiKey::fromRequest($request);

        if (!$apiKey) {
            throw new \App\Exceptions\InvalidApiKeyException();
        }

        if ($requireWrite && $apiKey->read_only) {
            throw new \App\Exceptions\ApiKeyAuthorizationException();
        }

        dispatch(function () use ($apiKey) {
            $apiKey->recordUsage();
        })->afterResponse();
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
