<?php

namespace App\Http\Controllers\API;

use App\Helpers\StaffingHelper;
use App\Http\Controllers\Controller;
use App\Models\Staffing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StaffingController extends Controller
{
    public function book(Request $request)
    {
        $request->validate([
            'cid' => 'required|integer',
            'position' => 'required|exists:positions,name',
            'message_id' => 'required|integer',
        ]);

        $staffing = Staffing::where('message_id', $request->input('message_id'))->first();
        $position = $staffing->positions()->where('name', $request->input('position'))->first();

        if (!$staffing || !$position) {
            return response()->json(['error' => 'Staffing or position not found'], 404);
        }

        if ($position->discord_user) {
            return response()->json(['error' => 'Position already booked'], 400);
        }

        if ($position->local_booking) {
            $position->discord_user = $request->user()->discord_id;
            $position->save();

            StaffingHelper::updateDiscordMessage($staffing);

            return response()->json([
                'message' => 'Position booked successfully',
            ]);
        }

        Http::withToken(config('booking.cc_api_token'))->post(
            config('booking.cc_api_url') . '/bookings/create', [
                'cid' => $request->input('cid'),
                'date' => $staffing->event->start_date,
                'position' => $position->name,
                'start_at' => $position->start_time,
                'end_at' => $position->end_time,
                'tag' => 3,
                'source' => 'Discord',
            ]
        );

        StaffingHelper::updateDiscordMessage($staffing);

        return response()->json([
            'message' => 'Position booked successfully',
        ], 200);
    }

    public function unbook(Request $request) 
    {
        $request->validate([
            'cid' => 'required|integer',
            'message_id' => 'required|integer',
        ]);

        $staffing = Staffing::where('message_id', $request->input('message_id'))->first();

        if (!$staffing) {
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        $position = $staffing->positions()->where('discord_user', $request->user()->discord_id)->get();

        if ($position->isEmpty()) {
            return response()->json(['error' => 'Position not found'], 404);
        }

        $position->each(function ($position) {

            if ($position->local_booking) {
                $position->discord_user = null;
                $position->save();
                return;
            }

            Http::withToken(config('booking.cc_api_token'))->delete(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);
        });

        StaffingHelper::updateDiscordMessage($staffing);

        return response()->json([
            'message' => 'Position unbooked successfully',
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(['data' => Staffing::all()], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Staffing $staffing)
    {
        return response()->json(['data' => $staffing], 200);
    }
}
