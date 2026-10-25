<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\VatsimNetworkStats;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(VatsimNetworkStats $networkStats): Response
    {
        return Inertia::render('Home', [
            // The public showcase, so only published events, even for staff who can see Drafts.
            'upcomingEvents' => EventResource::collection(Event::upcoming()->published()->limit(3)->get())->resolve(),
            'networkStats' => Inertia::defer(fn () => $networkStats->get()),
        ]);
    }
}
