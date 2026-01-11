<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Staffing;
use App\Services\StaffingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StaffingController extends Controller
{
    public function index()
    {
        $staffing = Staffing::with(['positions', 'instance.event'])->get();
        return response()->json(['data' => $staffing], 200);
    }

    public function show(Staffing $staffing)
    {
        $staffing->load(['positions', 'instance.event']);
        return response()->json(['data' => $staffing], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cid' => 'required|integer',
            'discord_user_id' => 'required|string',
            'position' => 'required|string',
            'section' => 'nullable|integer|between:1,4',
            'message_id' => 'required|string',
        ]);

        $staffing = Staffing::where('message_id', $request->message_id)->firstOrFail();

        $query = $staffing->positions()->where('callsign', $request->position);
        if ($request->filled('section')) {
            $query->where('section', $request->section);
        }
        $position = $query->firstOrFail();

        if ($position->discord_user || $position->booking_id) {
            return response()->json(['error' => 'Position already booked'], 422);
        }

        return DB::transaction(function () use ($staffing, $position, $request) {
            
            // Handle External Booking if not a local-only position
            if (!$position->local_booking) {
                $response = Http::retry(3, 1000)
                    ->withToken(config('booking.cc_api_token'))
                    ->post(config('booking.cc_api_url') . '/bookings/create', [
                        'cid' => $request->cid,
                        'date' => $staffing->instance->start_time->format('d/m/Y'),
                        'position' => $position->callsign,
                        'start_at' => Carbon::parse($position->start_time ?? $staffing->instance->start_time)->format('H:i'),
                        'end_at' => Carbon::parse($position->end_time ?? $staffing->instance->end_time)->format('H:i'),
                        'tag' => 3,
                        'source' => 'Discord',
                    ]);

                if ($response->failed()) {
                    return response()->json(['error' => 'External booking failed'], 500);
                }
                
                $position->booking_id = $response->json()['booking']['id'];
            }

            $position->discord_user = $request->discord_user_id;
            $position->save();

            StaffingService::updateDiscordMessage($staffing);

            return response()->json(['message' => 'Position booked successfully']);
        });
    }

    public function destroy(Request $request) 
    {
        $validated = $request->validate([
            'discord_user_id' => 'required|string',
            'message_id' => 'required|string',
            'position' => 'nullable|string',
            'section' => 'nullable|integer',
        ]);

        $staffing = Staffing::where('message_id', $validated['message_id'])->firstOrFail();

        $positions = $staffing->positions()
            ->where('discord_user', $validated['discord_user_id'])
            ->when(isset($validated['position']), fn($q) => $q->where('callsign', $validated['position']))
            ->when(isset($validated['section']), fn($q) => $q->where('section', $validated['section']))
            ->get();

        if ($positions->isEmpty()) {
            return response()->json(['error' => 'No bookings found for this user'], 404);
        }

        return DB::transaction(function () use ($staffing, $positions) {
            foreach ($positions as $position) {
                if ($position->booking_id) {
                    StaffingService::cancelExternalBooking($position->booking_id);
                }

                $position->update([
                    'discord_user' => null,
                    'booking_id' => null,
                ]);
            }

            StaffingService::updateDiscordMessage($staffing);

            return response()->json(['message' => 'Position unbooked successfully']);
        });
    }

    public function reset(Staffing $staffing)
    {
        try {
            StaffingService::resetAndSync($staffing);
            return response()->json(['message' => 'Staffing reset successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}