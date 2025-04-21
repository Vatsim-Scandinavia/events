<?php

namespace App\Http\Controllers\API;

use App\Exceptions\EventException;
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
        $staffing->loadMissing('event');

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

        if (!$staffing) {
            throw new EventException('Staffing  not found', 404);
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        $position = $request->input('section') ? $staffing->positions()->where('callsign', $request->input('position'))->where('section', $request->input('section'))->first() : $staffing->positions()->where('callsign', $request->input('position'))->first();

        if (!$position) {
            throw new EventException('Position not found', 404);
        }

        if ($position->discord_user) {
            throw new EventException('Position already booked', 422);
        }

        if ($position->local_booking) {
            $position->discord_user = $request->input('discord_user_id');
            $position->save();

            if (!StaffingHelper::updateDiscordMessage($staffing)) {
                return response()->json(['error' => 'Failed to update Discord message'], 500);
            }

            return response()->json([
                'message' => 'Position booked successfully',
            ]);
        }

        $response = Http::retry(config('booking.api_retry_times', 3), config('booking.api_retry_delay', 1000))->withToken(config('booking.cc_api_token'))->acceptJson()->post(
            config('booking.cc_api_url') . '/bookings/create', [
                'cid' => $request->input('cid'),
                'date' => Carbon::parse($staffing->event->start_date)->format('d/m/Y'),
                'position' => $position->callsign,
                'start_at' => Carbon::parse($position->start_time ?? $staffing->event->start_date)->format('H:i'),
                'end_at' => Carbon::parse($position->end_time ?? $staffing->event->end_date)->format('H:i'),
                'tag' => 3,
                'source' => 'Discord',
            ]
        );

        if ($response->failed()) {
            throw new EventException('Error booking position in CC. CC responded with error: ' . $response->json()['message'], 500);
        }

        $position->discord_user = $request->input('discord_user_id');
        $position->booking_id = $response->json()['booking']['id'];
        $position->save();

        StaffingHelper::updateDiscordMessage($staffing);

        return response()->json([
            'message' => 'Position booked successfully',
        ], 200);
    }

    public function update(Request $request, Staffing $staffing)
    {
        $data = $request->validate([
            'description' => 'nullable|string',
            'message_id' => 'nullable|integer',
            'section_1_title' => 'nullable|string',
            'section_2_title' => 'nullable|string',
            'section_3_title' => 'nullable|string',
            'section_4_title' => 'nullable|string',
        ]);

        foreach ($staffing->positions as $position) {
            if ($position->discord_user || $position->booking_id)
            {
                throw new EventException('Staffing cannot be updated because it has bookings.', 500);
            }
        }

        $updateData = array_filter($data, fn($value) => !is_null($value));

        if (!empty($updateData)) {
            $staffing->update($updateData);

            StaffingHelper::updateDiscordMessage($staffing);

            return response()->json([
                'message' => 'Staffing updated successfully',
            ], 200);
        }

        throw new EventException('No valid data provided to update', 422);
    }

    public function reset(Staffing $staffing)
    {
        StaffingHelper::resetStaffing($staffing);
        StaffingHelper::updateDiscordMessage($staffing);
        
        return response()->json([
            'message' => 'Staffing reset successfully',
        ], 200);

        throw new EventException('Failed to reset staffing. No valid parent or future child event found.', 500);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request) 
    {
        $validated = $request->validate([
            'discord_user_id' => 'required|integer',
            'message_id' => 'required|integer',
            'position' => 'nullable|exists:positions,callsign',
            'section' => 'nullable|integer|between:1,4',
        ]);

        $staffing = Staffing::where('message_id', $validated['message_id'])->first();
        
        if (!$staffing) {
            throw new EventException('Staffing not found', 404);
        }

        $positions = $staffing->positions()
        ->when(isset($validated['position']), fn($query) => $query->where('callsign', $validated['position']))
        ->when(isset($validated['section']), fn($query) => $query->where('section', $validated['section']))
        ->where('discord_user', $validated['discord_user_id'])
        ->get();
        
        if ($positions->isEmpty()) {
            throw new EventException('Position not found', 404);
        }

        foreach ($positions as $position) {
            if ($position->local_booking) {
                $position->update(['discord_user' => null]);
                continue;
            }
    
            // Attempt to unbook via external API
            $response = Http::retry(config('booking.api_retry_times', 3), config('booking.api_retry_delay', 1000))->withToken(config('booking.cc_api_token'))
                ->delete(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);
    
            if ($response->failed()) {
                throw new EventException('Error unbooking position in CC. CC responded with error: ' . $response->json()['message'], 500);
            }
    
            // Update position after successful API unbooking
            $position->update([
                'discord_user' => null,
                'booking_id' => null,
            ]);
        }

        StaffingHelper::updateDiscordMessage($staffing);

        return response()->json([
            'message' => 'Position unbooked successfully',
        ], 200);
    }
}
