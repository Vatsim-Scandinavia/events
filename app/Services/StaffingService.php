<?php

namespace App\Services;

use App\Models\Staffing;
use App\Models\EventInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service class for managing staffing operations.
 *
 * Handles staffing lifecycle including creation, updates, resets,
 * and synchronization with external Discord and booking systems.
 */
class StaffingService
{
    /**
     * Resets a staffing to the next available event instance and clears all position bookings.
     *
     * This method performs the following operations in a database transaction:
     * 1. Finds the next future instance of the associated event
     * 2. Clears all position bookings (booking_id and discord_user)
     * 3. Updates the staffing to point to the next instance
     * 4. Cancels external bookings via the booking API
     * 5. Updates the Discord message with reset flag
     *
     * @param Staffing $staffing The staffing record to reset
     * @return bool True on success
     * @throws \Exception If no future instances are found or Discord update fails
     */
    public static function resetAndSync(Staffing $staffing): bool
    {
        $bookingIds = $staffing->positions
            ->filter(fn($p) => $p->booking_id)
            ->pluck('booking_id')
            ->toArray();

        DB::transaction(function () use ($staffing) {
            $nextInstance = EventInstance::where('event_id', $staffing->instance->event_id)
                ->where('start_time', '>', \Carbon\Carbon::now())
                ->where('id', '!=', $staffing->event_instance_id)
                ->orderBy('start_time', 'asc')
                ->first();

            if (!$nextInstance) {
                throw new \Exception('No future event instances found to reset to.');
            }

            foreach ($staffing->positions as $position) {
                $position->booking_id = null;
                $position->discord_user = null;
                $position->save();
            }

            $staffing->event_instance_id = $nextInstance->id;
            $staffing->save();
        });

        $cancellationErrors = [];
        foreach ($bookingIds as $bookingId) {
            try {
                self::cancelExternalBooking($bookingId);
            } catch (\Exception $e) {
                $cancellationErrors[] = "Failed to cancel booking ID $bookingId: " . $e->getMessage();
            }
        }

        self::updateDiscordMessage($staffing->fresh(), true);

        if (!empty($cancellationErrors)) {
            Log::warning('Some external booking cancellations failed during staffing reset', [
                'staffing_id' => $staffing->id,
                'errors' => $cancellationErrors
            ]);
        }

        return true;
    }

    /**
     * Initializes a newly created staffing on Discord.
     *
     * Creates the Discord announcement message for the staffing sheet.
     * This should be called after a staffing record and its positions are saved.
     *
     * @param Staffing $staffing The newly created staffing record
     * @return array The Discord API response
     * @throws \Exception If the Discord API call fails
     */
    public static function setupStaffing(Staffing $staffing): array
    {
        $response = Http::retry(3, 1000)
            ->timeout(config('booking.http_timeout', 10))
            ->withToken(config('booking.discord_api_token'))
            ->asForm()
            ->post(config('booking.discord_api_url') . '/staffings/setup', [
                'id' => $staffing->id
            ]);

        if ($response->failed()) {
            throw new \Exception('Staffing created, but the Discord message failed to initialize.');
        }

        return $response->json();
    }

    /**
     * Updates the existing Discord announcement message for a staffing.
     *
     * Used when staffing details, positions, or bookings are modified.
     * Can optionally signal a reset operation to the Discord bot.
     *
     * @param Staffing $staffing The staffing record to update
     * @param bool $reset Whether this is a reset operation (clears all bookings on Discord)
     * @return array The Discord API response
     * @throws \Exception If the Discord API call fails
     */
    public static function updateDiscordMessage(Staffing $staffing, bool $reset = false): array
    {
        $payload = ['id' => $staffing->id];
        if ($reset) {
            $payload['reset'] = true;
        }

        $response = Http::retry(3, 1000)
            ->timeout(config('booking.http_timeout', 10))
            ->withToken(config('booking.discord_api_token'))
            ->asForm()
            ->post(config('booking.discord_api_url') . '/staffings/update', $payload);

        if ($response->failed()) {
            throw new \Exception('Database updated, but failed to sync with Discord.');
        }

        return $response->json();
    }

    /**
     * Cancels a booking via the external booking API.
     *
     * Calls the Control Center API to delete a booking.
     * 404 responses are treated as success (idempotent deletion).
     *
     * @param int $bookingId The booking ID to cancel
     * @return void
     * @throws \Exception If the API call fails (excluding 404)
     */
    protected static function cancelExternalBooking(int $bookingId): void
    {
        $response = Http::retry(3, 1000)
            ->timeout(config('booking.http_timeout', 10))
            ->withToken(config('booking.cc_api_token'))
            ->delete(config('booking.cc_api_url') . '/bookings/' . $bookingId);

        if ($response->failed() && $response->status() !== 404) {
            throw new \Exception("External booking cancellation failed for ID: $bookingId");
        }
    }

    /**
     * Checks if a staffing's current event instance has already ended.
     *
     * Used by scheduled commands to determine if a staffing needs to be
     * automatically moved to the next event instance.
     *
     * @param Staffing $staffing The staffing to check
     * @return bool True if the instance has ended and needs reset
     */
    public function needsReset(Staffing $staffing): bool
    {
        return $staffing->instance && $staffing->instance->end_time <= \Carbon\Carbon::now();
    }

    /**
     * Moves a staffing to the next future event instance.
     *
     * Finds the next occurrence of the event and updates the staffing's
     * event_instance_id. Does not clear bookings (use resetAndSync for that).
     *
     * @param Staffing $staffing The staffing to move
     * @return bool True if moved successfully, false if no next instance found
     */
    public function moveToNextInstance(Staffing $staffing): bool
    {
        if (!$staffing->instance) {
            return false;
        }

        $nextInstance = \App\Models\EventInstance::where('event_id', $staffing->instance->event_id)
            ->where('start_time', '>', \Carbon\Carbon::now())
            ->orderBy('start_time', 'asc')
            ->first();

        if (!$nextInstance) {
            return false;
        }

        return $staffing->update(['event_instance_id' => $nextInstance->id]);
    }
}