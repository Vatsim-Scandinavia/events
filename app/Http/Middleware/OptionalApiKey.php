<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

/**
 * Middleware to optionally authenticate requests using an API key.
 * If an API key is provided, it will be validated.
 * If no API key is provided, the request will proceed without authentication.
 */

class OptionalApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-KEY')
            ?? $request->header('Authorization');

        if ($token) {
            $token = preg_replace('/^Bearer\s+/i', '', $token);

            $apiKey = ApiKey::where('id', $token)->first();

            if ($apiKey) {
                dispatch(function () use ($apiKey) {
                    $apiKey->recordUsage();
                })->afterResponse();
                $request->attributes->set('api_key', $apiKey);
            }
        }

        return $next($request);
    }
}
