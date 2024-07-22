<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FrontController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $now = Carbon::now();
        $events = Event::whereBetween('start_date', [$now, $now->copy()->addDay()])
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get()
            ->filter(function ($event) {
                return \Auth::user()->can('view', $event) && \Auth::user()->can('view', $event->calendar);
            });

        return view('dashboard', compact('events'));
    }
}
