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
        $events = Event::where('start', '>=', Carbon::now())->get();

        $channels = $this->getGuildChannels();

        return view('staffing.create', compact('allData', 'channels'));
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
            'week_int' => 'required|integer|between:1,4',
            'section_1_title' => 'required',
            'section_2_title' => 'nullable',
            'section_3_title' => 'nullable',
            'section_4_title' => 'nullable',
            'restrict_booking' => 'required|integer',
        ]);

        $eventData = $this->getEvent($request->input('event'));

        // Staffing::create([
        //     'id' => $eventData->id,
        //     'title' => $eventData->title,
        //     'date' => $this->getDate($eventData->start),
        // ])

        var_dump($this->getDate($eventData->start));
    }

    protected function getEvent($id)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->withBasicAuth(Config::get('custom.forum_api_secret'), '')
                ->get(Config::get('custom.forum_api_url').'/'.$id);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }

    }

    protected function getDate($date)
    {
        $carbonDate = Carbon::parse($date);

        $dayofweek = $carbonDate->dayOfWeek->format('l');

        $currentDate = Carbon::now();

        while ($currentDate->dayOfWeek !== $dayofweek) {
            $currentDate->addDay();
        }

        return $currentDate;
    }

    /**
     * Display the specified resource.
     */
    public function show(Staffing $staffing)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Staffing $staffing)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Staffing $staffing)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Staffing $staffing)
    {
        //
    }
}
