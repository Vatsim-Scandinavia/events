<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $args = ''): Response
    {
        $key = ApiKey::find($request->bearerToken());

        if ($key === null || ($args == 'edit' && $key->readonly == true)) {
            // Exception for open calendar fetch
            if (preg_match('/^\/api\/calendars\/\d+\/events$/', $request->getRequestUri())) {
                $request->attributes->set('unauthenticated', true);

                return $next($request);
            } else {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 401);
            }
        }

        $key->update(['last_used_at', now()]);

        return $next($request);
    }
}
