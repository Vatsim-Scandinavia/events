<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Staffing;
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
                return !empty($item['recurrence']);
            });

            // Check for duplicate titles and select the newest entry for each unique title
            foreach ($filteredData as $item) {
                $title = $item['title'];

                // If a newer entry with the same title is found, replace the existing entry
                if (isset($allData[$title]) && strtotime($item['start']) > strtotime($allData[$title]['start'])) {
                    $allData[$title] = $item;
                } elseif (!isset($allData[$title])) {
                    $allData[$title] = $item;
                } elseif (Staffing::find($item['id'])) {
                    unset($allData[$title]);
                }
            }
        }

        $areas = Area::all();

        return view('staffing.create', compact('allData', 'areas'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
