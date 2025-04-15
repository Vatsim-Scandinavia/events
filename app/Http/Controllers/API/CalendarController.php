<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $calendars = Calendar::all();

        return response()->json(['data' => $calendars->values()], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'public' => 'required|boolean',
        ]);

        $calendar = Calendar::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'public' => $request->input('public'),
        ]);

        return response()->json([
            'success' => 'Calendar created',
            'data' => $calendar,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Calendar $calendar)
    {
        return response()->json(['calendar' => $calendar], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Calendar $calendar)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'public' => 'required|boolean',
        ]);

        $calendar->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'public' => $request->input('public'),
        ]);

        return response()->json([
            'success' => 'Calendar updated',
            'data' => $calendar,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Calendar $calendar)
    {
        $calendar->delete();

        return response()->json([
            'success' => 'Calendar deleted',
            'data' => $calendar,
        ], 200);
    }
}
