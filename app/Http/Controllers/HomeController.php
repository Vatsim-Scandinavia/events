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
            'upcomingEvents' => EventResource::collection(Event::upcoming()->limit(3)->get())->resolve(),
            'networkStats' => Inertia::defer(fn () => $networkStats->get()),
        ]);
    }
}
