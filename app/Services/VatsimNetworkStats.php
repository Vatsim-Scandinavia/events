<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VatsimNetworkStats
{
    private const FEED_URL = 'https://data.vatsim.net/v3/vatsim-data.json';

    private const CACHE_KEY = 'vatsim.network-stats';

    // The feed refreshes every 15 seconds; a minute is plenty for a landing page.
    private const CACHE_SECONDS = 60;

    // After a minute, stale stats are still served for this long while one request refreshes them after responding.
    private const STALE_SECONDS = 600;

    private const OBSERVER_FACILITY = 0;

    /**
     * @return array{pilots: int, controllers: int, users: int, busiestAirports: list<array{icao: string, departures: int, arrivals: int}>, updatedAt: string}|null
     */
    public function get(): ?array
    {
        try {
            return Cache::flexible(self::CACHE_KEY, [self::CACHE_SECONDS, self::STALE_SECONDS], fn () => $this->fetch());
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Could not load the VATSIM data feed.', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    private function fetch(): array
    {
        $feed = Http::timeout(5)->acceptJson()->get(self::FEED_URL)->throw()->json();

        $controllers = collect($feed['controllers'] ?? [])
            ->filter(fn (array $controller) => ($controller['facility'] ?? self::OBSERVER_FACILITY) !== self::OBSERVER_FACILITY);

        return [
            'pilots' => count($feed['pilots'] ?? []),
            'controllers' => $controllers->count(),
            'users' => $feed['general']['unique_users'] ?? 0,
            'busiestAirports' => $this->busiestAirports($feed['pilots'] ?? []),
            'updatedAt' => $feed['general']['update_timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{icao: string, departures: int, arrivals: int}>
     */
    private function busiestAirports(array $pilots, int $limit = 5): array
    {
        $airports = [];

        foreach ($pilots as $pilot) {
            foreach (['departure' => 'departures', 'arrival' => 'arrivals'] as $field => $counter) {
                $icao = strtoupper(trim($pilot['flight_plan'][$field] ?? ''));

                if ($icao === '') {
                    continue;
                }

                $airports[$icao] ??= ['icao' => $icao, 'departures' => 0, 'arrivals' => 0];
                $airports[$icao][$counter]++;
            }
        }

        return collect($airports)
            ->sortByDesc(fn (array $airport) => $airport['departures'] + $airport['arrivals'])
            ->take($limit)
            ->values()
            ->all();
    }
}
