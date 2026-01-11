<?php

namespace App\Http\Controllers;

use App\Exceptions\EventException;
use App\Models\Event;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\EventInstance;
use App\Services\StaffingService;

class StaffingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('index', Staffing::class);

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
            Log::error('Failed to refresh staffing', ['staffing_id' => $staffing->id, 'exception' => $e]);
            return redirect()->back()->withErrors(['error' => 'Failed to refresh staffing. Please try again or contact support.']);
        }
    }

    public function manualReset(Staffing $staffing)
    {
        $this->authorize('update', $staffing);

        try {
            StaffingService::resetAndSync($staffing);
            return redirect()->route('staffings.index')->withSuccess('Staffing reset successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to reset staffing', ['staffing_id' => $staffing->id, 'exception' => $e]);
            return redirect()->back()->withErrors(['error' => 'Failed to reset staffing. Please try again or contact support.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Staffing::class);

        // Get unique events that have at least one upcoming instance without staffing
        $events = Event::whereNotNull('recurrence_unit')
            ->whereHas('instances', function ($query) {
                $query->where('start_time', '>', now())
                    ->whereDoesntHave('staffing');
            })
            ->with(['instances' => function ($query) {
                // Load the instances so the blade can show the "Next:" date
                $query->where('start_time', '>', now())
                    ->whereDoesntHave('staffing')
                    ->orderBy('start_time', 'asc');
            }])
            ->get();

        $positions = $this->getPositions();
        $channels = $this->getGuildChannels(true);

        return view('staffing.create', compact('events', 'channels', 'positions'));
    }

    protected function getPositions()
    {
        return cache()->remember('staffing_positions', 300, function () {
            $apiUrl = config('booking.cc_api_url');
            
            if (!$apiUrl) {
                Log::warning('CC_API_URL not configured, returning empty positions list');
                return [];
            }
            
            try {
                $response = Http::timeout(config('booking.http_timeout', 5))->get($apiUrl . '/positions');
                if ($response->successful()) {
                    $positions = $response->json()['data'];
                    usort($positions, fn($a, $b) => strcmp($a['callsign'], $b['callsign']));
                    return $positions;
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch positions from API', ['exception' => $e, 'api_url' => $apiUrl]);
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
        $this->authorize('create', Staffing::class);

        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'description' => 'required|string',
            'channel_id' => 'required|string',
            'section_1_title' => 'required|string',
            'section_2_title' => 'nullable|string',
            'section_3_title' => 'nullable|string',
            'section_4_title' => 'nullable|string',
            'positions' => 'required|array',
            'positions.*.callsign' => 'required|string',
            'positions.*.section' => 'required|integer',
            'positions.*.start_time' => 'nullable|date',
            'positions.*.end_time' => 'nullable|date',
            'positions.*.local_booking' => 'nullable|boolean',
        ]);

        // 1. Check for duplicates
        $positions = collect($validated['positions']);
        foreach ($positions->groupBy('section') as $section => $posList) {
            $duplicates = $posList->map(fn($p) => strtoupper(trim($p['callsign'])))->duplicates();
            if ($duplicates->isNotEmpty()) {
                return redirect()->back()->withErrors(['positions' => "Duplicate callsign '{$duplicates->first()}' in section $section."])->withInput();
            }
        }

        // 2. Determine the correct instance behind the scenes
        $event = Event::findOrFail($validated['event_id']);
        $instance = $event->getDisplayInstance(); // Or $event->nextInstance

        if (!$instance) {
            return redirect()->back()->withErrors(['event_id' => 'This event has no upcoming instances to staff.'])->withInput();
        }

        // 3. Wrap Staffing creation in a transaction (external services handled separately)
        DB::beginTransaction();
        try {
            // Create the staffing record
            $staffing = Staffing::create([
                'event_instance_id' => $instance->id,
                'description' => $validated['description'],
                'channel_id' => $validated['channel_id'],
                'section_1_title' => $validated['section_1_title'],
                'section_2_title' => $validated['section_2_title'] ?? null,
                'section_3_title' => $validated['section_3_title'] ?? null,
                'section_4_title' => $validated['section_4_title'] ?? null,
            ]);

            // Create the positions
            foreach ($validated['positions'] as $positionData) {
                $staffing->positions()->create([
                    'callsign' => $positionData['callsign'],
                    'section' => $positionData['section'],
                    'start_time' => $positionData['start_time'] ?? null,
                    'end_time' => $positionData['end_time'] ?? null,
                    'local_booking' => $positionData['local_booking'] ?? false,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staffing creation failed', ['exception' => $e]);
            return redirect()->back()->withErrors(['error' => 'Failed to create staffing. Please try again.'])->withInput();
        }

        // Handle external service calls outside the transaction
        try {
            StaffingService::setupStaffing($staffing);
        } catch (\Exception $e) {
            Log::error('Staffing Discord setup failed', ['staffing_id' => $staffing->id, 'exception' => $e]);
            return redirect()->route('staffings.index')->withWarning('Staffing created successfully, but Discord setup failed. Please check the staffing manually.');
        }

        return redirect()->route('staffings.index')->withSuccess('Staffing created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Staffing $staffing)
    {
        $this->authorize('update', $staffing);

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
            'positions.*.start_time' => 'nullable|date',
            'positions.*.end_time' => 'nullable|date',
            'positions.*.local_booking' => 'nullable|boolean',
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

        // Handle Discord update separately to avoid affecting committed DB state
        try {
            StaffingService::updateDiscordMessage($staffing);
        } catch (\Exception $e) {
            Log::error('Discord message update failed', ['staffing_id' => $staffing->id, 'exception' => $e]);
            return redirect()->route('staffings.index')->withWarning('Staffing updated successfully, but Discord message update failed. Please check the message manually.');
        }

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
