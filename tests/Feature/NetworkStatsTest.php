<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NetworkStatsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const FEED_URL = 'data.vatsim.net/v3/vatsim-data.json';

    public function test_the_front_page_counts_who_is_online_and_ranks_the_busiest_airports(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::FEED_URL => Http::response([
            'general' => ['unique_users' => 4, 'update_timestamp' => '2026-10-18T12:34:56Z'],
            'pilots' => [
                ['flight_plan' => ['departure' => 'EKCH', 'arrival' => 'ESSA']],
                ['flight_plan' => ['departure' => 'EKCH', 'arrival' => 'ENGM']],
                ['flight_plan' => ['departure' => ' ekch ', 'arrival' => '']],
                ['flight_plan' => ['departure' => 'ESSA', 'arrival' => '']],
                ['flight_plan' => null],
            ],
            'controllers' => [
                ['facility' => 5],
                ['facility' => 0],
            ],
        ])]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('networkStats.pilots', 5)
                // Observers are connected but aren't controlling.
                ->where('networkStats.controllers', 1)
                ->where('networkStats.users', 4)
                ->where('networkStats.updatedAt', '2026-10-18T12:34:56Z')
                ->where('networkStats.busiestAirports', [
                    ['icao' => 'EKCH', 'departures' => 3, 'arrivals' => 0],
                    ['icao' => 'ESSA', 'departures' => 1, 'arrivals' => 1],
                    ['icao' => 'ENGM', 'departures' => 0, 'arrivals' => 1],
                ])
            )
        );
    }

    public function test_the_front_page_shows_no_stats_when_the_feed_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::FEED_URL => Http::response(status: 503)]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('networkStats', null))
        );
    }
}
