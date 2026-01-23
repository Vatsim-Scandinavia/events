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
        $token = $request->header('X-API-KEY')
            ?? $request->header('Authorization');

        if (!$token) {
            return response()->json([
                'error' => 'API_KEY_MISSING',
                'message' => 'Please provide an API key in the X-API-KEY or Authorization header.',
            ], 401);
        }

        $token = preg_replace('/^Bearer\s+/i', '', $token);
        $apiKey = ApiKey::where('id', $token)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'API_KEY_INVALID',
                'message' => 'The provided API key is invalid.',
            ], 401);
        }

        if ($requireWrite && $apiKey->read_only) {
            return response()->json([
                'error' => 'API_KEY_READ_ONLY',
                'message' => 'The provided API key does not have write permissions.',
            ], 403);
        }

        dispatch(function () use ($apiKey) {
            $apiKey->recordUsage();
        })->afterResponse();
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
