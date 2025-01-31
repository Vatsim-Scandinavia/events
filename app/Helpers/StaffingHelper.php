<?php

namespace App\Helpers;

use App\Models\Event;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class StaffingHelper
{
    public static function resetStaffing(Event $event, Staffing $staffing) 
    {
        if ($event->recurrence_interval && $event->recurrence_unit) {
            $childEvent = $event->recurrences()->where('start_date', '>', Carbon::now())->first();
            $staffing->event()->associate($childEvent);
            $staffing->save();
        }

        // Clear all bookings
        $staffing->positions()->update([
            'discord_user' => null,
        ]);

        StaffingHelper::updateDiscordMessage($staffing);

    }

    public static function updateDiscordMessage(Staffing $staffing)
    {
        // Send a api request to the discord bot to update the staffing message
        Http::withToken(config('discord.bot_token'))->patch(
            config('discord.api_url') . '/staffing/update', [
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
    }
}