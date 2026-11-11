<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\VatsimNetworkStats;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The front page. It's the public showcase, so it lists only published events, even for staff
     * who can see Drafts: the next four, the first featured in the hero and the rest in the grid.
     */
    public function index(VatsimNetworkStats $networkStats): Response
    {
        return Inertia::render('Home', [
            'upcomingEvents' => EventResource::collection(Event::upcoming()->published()->limit(4)->get())->resolve(),
            'networkStats' => Inertia::defer(fn () => $networkStats->get()),
        ]);
    }
}
