<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    /**
     * Show the landing page if not logged in, or redirect if logged in.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $now = Carbon::now();
        $events = Event::where('start_date', '>=', $now)
            ->orderBy('start_date', 'asc')
            ->get()
            ->filter(function ($event) {
                return $event->calendar->public;
            })
            ->take(5);

        return view('welcome', compact('events'));
    }
}
