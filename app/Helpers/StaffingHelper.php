<?php

namespace App\Helpers;

use App\Exceptions\EventException;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class StaffingHelper
{
    public static function resetStaffing(Staffing $staffing, $route = null) 
    {
        $event = $staffing->event;

        $childEvent = $event->parent()->exists() ? $event->parent->children()->where('start_date', '>', Carbon::now())->first() : $event->children()->where('start_date', '>', Carbon::now())->first();

        if(!$childEvent) {
            throw new EventException('No child event found', 500);
            return false;
        }

        $staffing->event()->associate($childEvent);
        $staffing->positions()->each(function($position) use ($staffing, $route) {
            if ($position->booking_id) {
                $bookingExists = Http::retry(config('booking.api_retry_times', 3), config('booking.api_retry_delay', 1000))->withToken(config('booking.cc_api_token'))->acceptJson()->get(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);

                if($bookingExists->ok()) {
                    $response = Http::retry(config('booking.api_retry_times', 3), config('booking.api_retry_delay', 1000))->withToken(config('booking.cc_api_token'))->acceptJson()->delete(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);

                    if ($response->failed()) {
                        throw new EventException('Failed to delete booking position: '. $position->callsign . 'Error: ' . $response->body(), 500, null, $route);
                    }
                }

                $position->booking_id = null;
            }

            $position->discord_user = null;
            $position->save();
        });

        $staffing->save();

        return true;
    }

    public static function updateDiscordMessage(Staffing $staffing, $reset = null, $route = null)
    {
        $payload = [
            'id' => $staffing->id
        ];

        if ($reset) {
            $payload['reset'] = $reset;
        }

        // Send a api request to the discord bot to update the staffing message
        $response = Http::retry(config('booking.api_retry_times', 3), config('booking.api_retry_delay', 1000))->withToken(config('booking.discord_api_token'))->asForm()->post(config('booking.discord_api_url') . '/staffings/update', $payload);

        if ($response->failed()) {
            throw new EventException('Failed to update Discord message.', 500, null, $route);
        }

        return $response->json();
    }

    public static function setupStaffing(Staffing $staffing, $route = null)
    {
        $response = Http::retry(config('booking.api_retry_times', 3), config('booking.api_retry_delay', 1000))->withToken(config('booking.discord_api_token'))->asForm()->post(config('booking.discord_api_url') . '/staffings/setup', [
            'id' => $staffing->id
        ]);

        if ($response->failed()) {
            throw new EventException('Staffing message was not created. Please contact the Tech Team.', 500, null, $route);
        }

        return $response->json();
    }
}