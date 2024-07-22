<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Calendar $calendar) 
    {
        $events = $calendar
            ->events()
            ->get();

        return response()->json(['data' => $events->values()], 200);
    }
}
