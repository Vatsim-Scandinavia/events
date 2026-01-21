<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if ($token) {
            $apiKey = ApiKey::where('id', $token)->first();

            if ($apiKey) {
                dispatch(function () use ($apiKey) {
                    $apiKey->recordUsage();
                })->afterResponse();
            }

            return $next($request);
        }

        return $next($request);
    }
}
