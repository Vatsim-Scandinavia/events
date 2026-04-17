<?php

namespace App\Services;

use App\Clients\ControlCenterClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ControlCenterService
{
    /**
     * How long to cache the positions list from Control Center (in seconds).
     */
    protected const POSITIONS_CACHE_TTL = 3600; // 1 hour

    protected const POSITIONS_CACHE_KEY = 'control_center_positions';

    public function __construct(private ControlCenterClient $client) {}

    /**
     * Fetch all known ATC positions from Control Center.
     * Results are cached for {@see POSITIONS_CACHE_TTL} seconds.
     *
     * @return array  Array of position objects as returned by the CC API.
     */
    public function getPositions(): array
    {
        return Cache::remember(self::POSITIONS_CACHE_KEY, self::POSITIONS_CACHE_TTL, function () {
            $data = $this->client->get('/api/positions');

            if ($data === null) {
                Log::warning('ControlCenter: failed to fetch positions, returning empty list');
                return [];
            }

            return $data['data'] ?? $data;
        });
    }

    /**
     * Create a booking in Control Center and return the booking ID, or null on failure.
     *
     * Expected $data keys:
     *   - cid       int     VATSIM CID of the controller
     *   - date      string  Date in d/m/Y format
     *   - position  string  Position callsign (e.g. "ESSA_APP")
     *   - start_at  string  Start time in H:i format (UTC)
     *   - end_at    string  End time in H:i format (UTC)
     *   - tag       int     Booking tag (3 = event)
     *   - source    string  Source identifier (e.g. "Events")
     *
     * @param array $data
     * @return int|null  The new booking ID from Control Center, or null on failure.
     */
    public function createBooking(array $data): ?int
    {
        $response = $this->client->post('/api/bookings/create', $data);

        if ($response === null) {
            Log::warning('ControlCenter: createBooking returned null', ['data' => $data]);
            return null;
        }

        $id = $response['booking']['id'] ?? $response['data']['id'] ?? $response['id'] ?? null;

        if ($id === null) {
            Log::warning('ControlCenter: createBooking response missing ID', ['response' => $response]);
        }

        return $id !== null ? (int) $id : null;
    }

    /**
     * Delete a booking from Control Center.
     *
     * @param int $bookingId  The Control Center booking ID to remove.
     * @return bool           True if successfully deleted (or already gone), false on error.
     */
    public function deleteBooking(int $bookingId): bool
    {
        $result = $this->client->delete('/api/bookings/' . $bookingId);

        if (!$result) {
            Log::warning('ControlCenter: deleteBooking failed', ['booking_id' => $bookingId]);
        }

        return $result;
    }
}
