<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $check = false;
        if(\Auth::check()) {
            $user = \Auth::user();
            $check = $user->groups->where('id', '<=', 2)->isNotEmpty();
        }

        if(!$check) {
            auth()->logout();
            return redirect()->route('welcome')->withError('You do not have permission to access this system.');
        }
        
        return $next($request);
    }
}
