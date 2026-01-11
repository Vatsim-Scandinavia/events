<?php

namespace App\Http\Controllers;

use App\Exceptions\EventException;
use App\Models\Event;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use App\Models\EventInstance;
use App\Services\StaffingService;

class StaffingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staffings = Staffing::with(['instance.event', 'positions'])->get();

        return view('staffing.index', compact('staffings'));
    }

    public function refresh(Staffing $staffing)
    {
        $this->authorize('update', $staffing);

        try {
            StaffingService::updateDiscordMessage($staffing);

            return redirect()->route('staffings.index')->withSuccess('Staffing refreshed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to refresh staffing: ' . $e->getMessage()]);
        }
    }

    public function manualReset(Staffing $staffing)
    {
        $this->authorize('update', $staffing);

        try {
            StaffingService::resetAndSync($staffing);
            return redirect()->route('staffings.index')->withSuccess('Staffing reset successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reset staffing: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Staffing::class);

        $events = Event::whereNotNull('recurrence_unit')
            ->whereHas('instances', function ($query) {
                $query->where('start_time', '>', now())
                    ->whereDoesntHave('staffing');
            })
            ->whereDoesntHave('instances.staffing') 
            ->get();

        $positions = $this->getPositions();
        $channels = $this->getGuildChannels(true);

        return view('staffing.create', compact('events', 'channels', 'positions'));
    }

    protected function getPositions()
    {
        return cache()->remember('staffing_positions', 300, function () {
            $response = Http::get(config('booking.cc_api_url').'/positions');
            if ($response->successful()) {
                $positions = $response->json()['data'];
                usort($positions, fn($a, $b) => strcmp($a['callsign'], $b['callsign']));
                return $positions;
            }
            return [];
        });
    }

    protected function getGuildChannels($create = null)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.Config::get('custom.discord_bot_token'),
                'Content-Type' => 'application/json',
            ])->get('https://discord.com/api/v10/guilds/'.Config::get('custom.discord_guild_id').'/channels');

            if ($response->successful()) {
                $channelsData = $response->json();

                $filteredChannels = array_filter($channelsData, function ($channel) {
                    return isset($channel['type']) && $channel['type'] == 0;
                });

                $filteredChannels = array_filter($filteredChannels, function ($channel) {
                    return strpos($channel['name'], 'staffing') !== false || strpos($channel['name'], 'signup') !== false;
                });

                $filteredChannels = array_values($filteredChannels);

                if ($create) {
                    $existingChannelIds = Staffing::pluck('channel_id')->toArray();

                    $filteredChannels = array_filter($filteredChannels, function ($channel) use ($existingChannelIds) {
                        return !in_array($channel['id'], $existingChannelIds);
                    });
                }

                usort($filteredChannels, function ($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });

                return $filteredChannels;
            } else {
                throw new \Exception('Unable to fetch channels. HTTP status code: '.$response->status());
            }
        } catch (\Exception $e) {
            return ['Error: '.$e->getMessage()];
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'description' => 'required|string',
            'section_1_title' => 'required|string',
            'positions' => 'required|array',
            'positions.*.callsign' => 'required|string',
            'positions.*.section' => 'required|integer',
        ]);

        $this->authorize('create', Staffing::class);

        $positions = collect($request->positions);
        foreach ($positions->groupBy('section') as $section => $posList) {
            $duplicates = $posList->map(fn($p) => strtoupper(trim($p['callsign'])))->duplicates();

            if ($duplicates->isNotEmpty()) {
                return redirect()->back()->withErrors(['positions' => "Duplicate callsign '{$duplicates->first()}' in section $section."])->withInput();
            }
        }

        StaffingService::setupStaffing($staffing);

        return redirect()->route('staffings.index')->withSuccess('Staffing created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Staffing $staffing)
    {
        $staffing->load(['instance.event', 'positions']);
        
        $positions = $this->getPositions(); 
        $channels = $this->getGuildChannels(true);

        return view('staffing.edit', compact('staffing', 'positions', 'channels'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Staffing $staffing)
    {
        $this->validate($request, [
            'description' => 'required',
            'section_1_title' => 'required',
            'section_2_title' => 'nullable',
            'section_3_title' => 'nullable',
            'section_4_title' => 'nullable',
            'positions' => 'required|array',
            'positions.*.callsign' => 'required|string',
            'positions.*.section' => 'required|integer',
        ]);
        
        $this->authorize('update', $staffing);

        foreach ($staffing->positions as $position) {
            if ($position->discord_user || $position->booking_id) {
                throw new EventException('Staffing cannot be edited because it has bookings.', 500, null, 'staffings.index');
            }
        }

        $submittedPositions = collect($request->positions);
        
        foreach ($submittedPositions->groupBy('section') as $section => $posList) {
            $duplicates = $posList->map(fn($p) => strtoupper(trim($p['callsign'])))->duplicates();
            if ($duplicates->isNotEmpty()) {
                return redirect()->back()->withErrors(['positions' => "Duplicate callsign '{$duplicates->first()}' in section $section."])->withInput();
            }
        }

        \DB::transaction(function () use ($staffing, $request, $submittedPositions) {
            $staffing->update($request->only([
                'description', 'section_1_title', 'section_2_title', 'section_3_title', 'section_4_title',
            ]));

            $keptIds = $submittedPositions->pluck('id')->filter()->toArray();
            $staffing->positions()->whereNotIn('id', $keptIds)->delete();

            foreach ($submittedPositions as $data) {
                $staffing->positions()->updateOrCreate(
                    ['id' => $data['id'] ?? null],
                    [
                        'callsign' => $data['callsign'],
                        'section' => $data['section'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'local_booking' => $data['local_booking'],
                    ]
                );
            }
        });

        StaffingService::updateDiscordMessage($staffing);

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
                throw new EventException('Staffing cannot be deleted because it has bookings.', 500, null, 'staffings.index');
            }
        });

        $staffing->delete();

        return redirect()->route('staffings.index')->withSuccess('Staffing deleted successfully.');
    }
}
