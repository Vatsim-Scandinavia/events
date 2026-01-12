<?php

namespace App\Services;

use App\Models\Staffing;
use App\Models\EventInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StaffingService
{
    /**
     * Resets a staffing to the next available instance and clears bookings.
     */
    public static function resetAndSync(Staffing $staffing)
    {
        // Collect booking IDs before transaction
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

        // Cancel external bookings after successful DB commit
        foreach ($bookingIds as $bookingId) {
            self::cancelExternalBooking($bookingId);
        }

        self::updateDiscordMessage($staffing->fresh(), true);

        return true;
    }

    /**
     * Initial setup for a new staffing on Discord.
     */
    public static function setupStaffing(Staffing $staffing)
    {
        $response = Http::retry(3, 1000)
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
     * Sends an update to the existing Discord announcement.
     */
    public static function updateDiscordMessage(Staffing $staffing, bool $reset = false)
    {
        $payload = ['id' => $staffing->id];
        if ($reset) {
            $payload['reset'] = true;
        }

        $response = Http::retry(3, 1000)
            ->withToken(config('booking.discord_api_token'))
            ->asForm()
            ->post(config('booking.discord_api_url') . '/staffings/update', $payload);

        if ($response->failed()) {
            throw new \Exception('Database updated, but failed to sync with Discord.');
        }

        return $response->json();
    }

    /**
     * Private helper for external API cleanup.
     */
    protected static function cancelExternalBooking($bookingId)
    {
        $response = Http::retry(3, 1000)
            ->withToken(config('booking.cc_api_token'))
            ->delete(config('booking.cc_api_url') . '/bookings/' . $bookingId);

        // We ignore 404 because if it's already gone, our goal is achieved.
        if ($response->failed() && $response->status() !== 404) {
            throw new \Exception("External booking cancellation failed for ID: $bookingId");
        }
    }

    /**
     * Check if the staffing's current instance has already ended.
     */
    public function needsReset(Staffing $staffing): bool
    {
        // Note the use of ->instance instead of ->event_instance
        return $staffing->instance && $staffing->instance->end_time <= \Carbon\Carbon::now();
    }

    // app/Services/StaffingService.php

    /**
     * Find the next instance and move the staffing to it.
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