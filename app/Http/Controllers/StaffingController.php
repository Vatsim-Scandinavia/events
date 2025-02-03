<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class StaffingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staffings = Staffing::all();

        return view('staffing.index', compact('staffings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Staffing::class);

        $events = Event::where('start_date', '>=', Carbon::now())->get();

        $positions = $this->getPositions();

        $channels = $this->getGuildChannels();

        return view('staffing.create', compact('events', 'channels', 'positions'));
    }

    protected function getPositions()
    {
        try {
            $response = Http::get(config('booking.cc_api_url').'/positions');

            if ($response->successful()) {
                return $response->json()['data'];
            } else {
                return 'Error: Unable to fetch positions. HTTP status code: '.$response->status();
            }
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    protected function getGuildChannels()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.Config::get('custom.discord_bot_token'),
                'Content-Type' => 'application/json',
            ])->get('https://discord.com/api/v10/guilds/'.Config::get('custom.discord_guild_id').'/channels');

            if ($response->successful()) {
                $channelsData = $response->json();

                // Filter channels where 'type' is equal to 0
                $filteredChannels = array_filter($channelsData, function ($channel) {
                    return isset($channel['type']) && $channel['type'] == 0;
                });

                // Reset array keys to start from 0 if needed
                $filteredChannels = array_values($filteredChannels);

                return $filteredChannels;
            } else {
                return 'Error: Unable to fetch guild channels. HTTP status code: '.$response->status();
            }
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'event' => 'required|integer',
            'description' => 'required',
            'channel_id' => 'required|integer',
            'section_1_title' => 'required',
            'section_2_title' => 'nullable',
            'section_3_title' => 'nullable',
            'section_4_title' => 'nullable',
            'positions' => 'required|array',
        ]);

        $this->authorize('create', Staffing::class);

        // Check for duplicate positions within sections
        $positions = $request->input('positions');
        $sections = [];

        foreach ($positions as $position) {
            $section = $position['section']; 
            $callsign = $position['callsign'];

            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }

            if (in_array($callsign, $sections[$section])) {
                return redirect()->back()->withErrors(['positions' => "Duplicate callsign '$callsign' found in section '$section'."]);
            }

            $sections[$section][] = $callsign;
        }

        $staffing = Staffing::create([
            'description' => $request->input('description'),
            'channel_id' => $request->input('channel_id'),
            'section_1_title' => $request->input('section_1_title'),
            'section_2_title' => $request->input('section_2_title'),
            'section_3_title' => $request->input('section_3_title'),
            'section_4_title' => $request->input('section_4_title'),
        ]);

        if (!$request->input('event')) {
            return redirect()->route('staffings.index')->withError('Event not found.');
        }

        $event = Event::findOrFail($request->input('event'));
        $staffing->event()->associate($event);
        $staffing->save();

        $staffing->positions()->createMany($request->input('positions'));

        return redirect()->route('staffings.index')->withSuccess('Staffing created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Staffing $staffing)
    {
        $this->authorize('update', $staffing);

        foreach ($staffing->positions as $position) {
            if ($position->discord_user || $position->booking_id)
            {
                return redirect()->route('staffings.index')->withErrors('Staffing cannot be edited because it has bookings.');
            }
        }

        $events = $staffing->event()->first()->children()->where('start_date', '>', Carbon::now())->get() ?? $staffing->event()->first()->parent()->children()->where('start_date', '>', Carbon::now())->get();

        $positions = $this->getPositions();

        $channels = $this->getGuildChannels();

        $positionIndex = 0;

        return view('staffing.edit', compact('staffing', 'events', 'channels', 'positions', 'positionIndex'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Staffing $staffing)
    {
        $this->validate($request, [
            'event' => 'required|integer',
            'description' => 'required',
            'channel_id' => 'required|integer',
            'section_1_title' => 'required',
            'section_2_title' => 'nullable',
            'section_3_title' => 'nullable',
            'section_4_title' => 'nullable',
            'positions' => 'required|array',
        ]);

        $this->authorize('update', $staffing);

        foreach ($staffing->positions as $position) {
            if ($position->discord_user || $position->booking_id)
            {
                return redirect()->route('staffings.index')->withErrors('Staffing cannot be updated because it has bookings.');
            }
        }

        // Check for duplicate positions within sections
        $positions = $request->input('positions');
        $sections = [];

        foreach ($positions as $position) {
            $section = $position['section']; 
            $callsign = $position['callsign'];

            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }

            if (in_array($callsign, $sections[$section])) {
                return redirect()->back()->withErrors(['positions' => "Duplicate callsign '$callsign' found in section '$section'."]);
            }

            $sections[$section][] = $callsign;
        }

        $staffing->update([
            'description' => $request->input('description'),
            'channel_id' => $request->input('channel_id'),
            'section_1_title' => $request->input('section_1_title'),
            'section_2_title' => $request->input('section_2_title'),
            'section_3_title' => $request->input('section_3_title'),
            'section_4_title' => $request->input('section_4_title'),
        ]);

        // Ensure event is valid
        if (!$request->input('event')) {
            return redirect()->route('staffings.index')->withError('Event not found.');
        }

        $event = Event::findOrFail($request->input('event'));
        $staffing->event()->associate($event);
        $staffing->save();

        // Sync positions: delete removed ones, update existing, and add new ones
        $staffing->positions()->delete();  // Remove old positions
        $staffing->positions()->createMany($positions); // Add new ones

        return redirect()->route('staffings.index')->withSuccess('Staffing updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Staffing $staffing)
    {
        $this->authorize('destroy', $staffing);

        $staffing->positions()->each(function ($position) {
            if ($position->discord_user || $position->booking_id)
            {
                return redirect()->route('staffings.index')->withError('Staffing cannot be deleted because it has bookings.');
            }
        });

        $staffing->delete();

        return redirect()->route('staffings.index')->withSuccess('Staffing deleted successfully.');
    }
}
