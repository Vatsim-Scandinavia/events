<?php

namespace App\Http\Controllers\API;

use App\Helpers\StaffingHelper;
use App\Http\Controllers\Controller;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StaffingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staffing = Staffing::all();
        $staffing->loadMissing('positions');

        return response()->json(['data' => $staffing], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Staffing $staffing)
    {
        $staffing->loadMissing('positions');
        $staffing->loadMissing('event');
        return response()->json(['data' => $staffing], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cid' => 'required|integer',
            'discord_user_id' => 'required|integer',
            'position' => 'required|exists:positions,callsign',
            'section' => 'nullable|integer|between:1,4',
            'message_id' => 'required|integer',
        ]);

        $staffing = Staffing::where('message_id', $request->input('message_id'))->first();
        $position = $request->input('section') ? $staffing->positions()->where('callsign', $request->input('position'))->where('section', $request->input('section'))->first() : $staffing->positions()->where('callsign', $request->input('position'))->first();

        if (!$staffing || !$position) {
            return response()->json(['error' => 'Staffing or position not found'], 404);
        }

        if ($position->discord_user) {
            return response()->json(['error' => 'Position already booked'], 400);
        }

        if ($position->local_booking) {
            $position->discord_user = $request->input('discord_user_id');
            $position->save();

            // StaffingHelper::updateDiscordMessage($staffing);

            return response()->json([
                'message' => 'Position booked successfully',
            ]);
        }

        $response = Http::withToken(config('booking.cc_api_token'))->post(
            config('booking.cc_api_url') . '/bookings/create', [
                'cid' => $request->input('cid'),
                'date' => Carbon::parse($staffing->event->start_date)->format('d/m/Y'),
                'position' => $position->callsign,
                'start_at' => Carbon::parse($position->start_time)->format('H:i'),
                'end_at' => Carbon::parse($position->end_time)->format('H:i'),
                'tag' => 3,
                'source' => 'Discord',
            ]
        );

        if (!$response->successful()) {
            return response()->json(['error' => 'Error booking position'], 500);
        }

        if ($response->successful()) {
            $position->discord_user = $request->input('discord_user_id');
            $position->booking_id = $response->json()['booking']['id'];
            $position->save();
        }

        // StaffingHelper::updateDiscordMessage($staffing);

        return response()->json([
            'message' => 'Position booked successfully',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request) 
    {
        $request->validate([
            'cid' => 'required|integer',
            'discord_user_id' => 'required|integer',
            'position' => 'nullable|exists:positions,callsign',
            'message_id' => 'required|integer',
        ]);

        $staffing = Staffing::where('message_id', $request->input('message_id'))->first();
        
        if (!$staffing) {
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        $position = $request->input('section') ? $staffing->positions()->where('callsign', $request->input('position'))->where('section', $request->input('section'))->get() : $staffing->positions()->where('callsign', $request->input('position'))->get();

        if ($position->isEmpty()) {
            return response()->json(['error' => 'Position not found'], 404);
        }

        $position->each(function ($position) {

            if ($position->local_booking) {
                $position->discord_user = null;
                $position->save();
                return;
            }

            $response = Http::withToken(config('booking.cc_api_token'))->delete(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);

            if (!$response->successful()) {
                return response()->json(['error' => 'Error unbooking position'], 500);
            }

            $position->discord_user = null;
            $position->booking_id = null;
            $position->save();
        });

        // StaffingHelper::updateDiscordMessage($staffing);

        return response()->json([
            'message' => 'Position unbooked successfully',
        ], 200);
    }
}
