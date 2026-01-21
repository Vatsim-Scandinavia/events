<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            ?? $request->header('Authorization')
            ?? $request->query('api_key');

        if (!$token) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key via X-API-KEY header or api_key parameter.',
            ], 401);
        }

        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        $apiKey = ApiKey::where('id', $token)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid.',
            ], 401);
        }

        if ($requireWrite && $apiKey->read_only) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => 'The provided API key is read-only.',
            ], 403);
        }

        dispatch(function () use ($apiKey) {
            $apiKey->recordUsage();
        })->afterResponse();

        $request->attributes->set('api_key', $apiKey);
        
        return $next($request);
    }
}
