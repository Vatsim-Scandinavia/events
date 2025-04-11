<?php

namespace App\Helpers;

use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class StaffingHelper
{
    public static function resetStaffing(Staffing $staffing) 
    {
        $event = $staffing->event;

        $childEvent = $event->parent()->exists() ? $event->parent->children()->where('start_date', '>', Carbon::now())->first() : $event->children()->where('start_date', '>', Carbon::now())->first();

        if(!$childEvent) {
            throw new \Exception('No future events found.');
            return false;
        }

        $staffing->event()->associate($childEvent);
        $staffing->positions()->each(function($position) {
            if ($position->booking_id) {
                $bookingExists = Http::withToken(config('booking.cc_api_token'))->acceptJson()->get(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);

                if($bookingExists->ok()) {
                    $response = Http::withToken(config('booking.cc_api_token'))->acceptJson()->delete(config('booking.cc_api_url') . '/bookings/' . $position->booking_id);

                    if ($response->failed()) {
                        throw new \Exception('Failed to delete booking. Error: ' . $response->body());
                        return false;
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

    public static function updateDiscordMessage(Staffing $staffing, $reset = null)
    {
        $payload = [
            'id' => $staffing->id
        ];

        if ($reset) {
            $payload['reset'] = $reset;
        }

        // Send a api request to the discord bot to update the staffing message
        $response = Http::withToken(config('booking.discord_api_token'))->asForm()->post(config('booking.discord_api_url') . '/staffings/update', $payload);

        if ($response->failed()) {
            throw new \Exception('Failed to update Discord message. Error: ' . $response->body());
            return false;
        }

        return $response->json();
    }

    public static function setupStaffing(Staffing $staffing)
    {
        $response = Http::withToken(config('booking.discord_api_token'))->asForm()->post(config('booking.discord_api_url') . '/staffings/setup', [
            'id' => $staffing->id
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to setup Discord message.');
            return false;
        }

        return $response->json();
    }
}