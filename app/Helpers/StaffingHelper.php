<?php

namespace App\Helpers;

use App\Models\Event;
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
        $staffing->positions()->update(['discord_user' => null]);
        $staffing->save();

        return true;
    }

    public static function updateDiscordMessage(Staffing $staffing)
    {
        // Send a api request to the discord bot to update the staffing message
        $response = Http::withToken(config('booking.discord_api_token'))->patch(
            config('booking.discord_api_url') . '/staffing/update', [
                'channel_id' => $staffing->channel_id,
                'message_id' => $staffing->message_id,
                'section_1_title' => $staffing->section_1_title,
                'section_2_title' => $staffing->section_2_title,
                'section_3_title' => $staffing->section_3_title,
                'section_4_title' => $staffing->section_4_title,
                'positions' => $staffing->positions->map(function ($position) {
                    return [
                        'name' => $position->name,
                        'discord_user' => $position->discord_user,
                        'section' => $position->section,
                        'start_time' => $position->start_time,
                        'end_time' => $position->end_time,
                    ];
                }),
            ]
        );

        if ($response->failed()) {
            throw new \Exception('Failed to update Discord message.');
            return false;
        }

        return $response->json();
    }
}