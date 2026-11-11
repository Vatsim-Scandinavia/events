<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a page visited by an old link, a plain ID or one from before a rename, to the event's
 * current URL, permanently so search engines follow. Only for someone who may see the event:
 * the current URL carries its name, which a Draft mustn't give away.
 *
 * Browsers would otherwise keep a permanent redirect for good, and an event renamed and later
 * given its old name back would then send them round in a circle, so it's marked no-store.
 */
class RedirectToCurrentEventUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $event = $route->parameter('event');

        if ($event instanceof Event && $route->originalParameter('event') !== $event->getRouteKey() && Gate::allows('view', $event)) {
            $query = $request->getQueryString();

            return redirect()
                ->to(route($route->getName(), $route->parameters()).($query ? "?{$query}" : ''), 301)
                ->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
