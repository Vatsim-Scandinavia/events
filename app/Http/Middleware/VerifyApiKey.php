<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $hash = hash('sha256', $token);
        $key  = ApiKey::where('key', $hash)->first();

        if (! $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $key->update(['last_used_at' => now()]);

        if ($key->read_only && ! in_array($request->method(), ['GET', 'HEAD'])) {
            return response()->json(['error' => 'This API key is read-only'], 403);
        }

        return $next($request);
    }
}
