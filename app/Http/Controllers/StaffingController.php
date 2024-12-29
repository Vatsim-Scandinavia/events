<?php

namespace App\Http\Controllers;

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
        $client = Http::withHeaders([
            'Accept' => 'application/json',
        ])
            ->withBasicAuth(Config::get('custom.forum_api_secret'), '');

        $allData = []; // Initialize an empty array to store the combined results

        for ($currentPage = 1; $currentPage <= 2; $currentPage++) {
            $response = $client->get(Config::get('custom.forum_api_url'), [
                'perPage' => 25,
                'hidden' => 0,
                'sortDir' => 'desc',
                'calendars' => Config::get('custom.forum_calendar_type'),
                'page' => $currentPage,
            ]);

            $currentData = $response->json('results');

            // Filter out data where the "recurrence" property is null
            $filteredData = array_filter($currentData, function ($item) {
                return ! empty($item['recurrence']);
            });

            // Check for duplicate titles and select the newest entry for each unique title
            foreach ($filteredData as $item) {
                $title = $item['title'];

                // If a newer entry with the same title is found, replace the existing entry
                if (isset($allData[$title]) && strtotime($item['start']) > strtotime($allData[$title]['start'])) {
                    $allData[$title] = $item;
                } elseif (! isset($allData[$title])) {
                    $allData[$title] = $item;
                } elseif (Staffing::find($item['id'])) {
                    unset($allData[$title]);
                }
            }
        }

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
