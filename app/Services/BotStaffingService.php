<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BotStaffingService
{
    /**
     * Return all staffings that have both a channel and a message ID bound,
     * formatted for the bot's list endpoint.
     */
    public function getAll(): array
    {
        $events = Event::whereHas('staffing', function ($q) {
            $q->whereNotNull('discord_channel_id')->whereNotNull('discord_message_id');
        })->with([
            'staffing.sections.positions',
            'occurrences' => fn($q) => $q
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time'),
        ])->get();

        $result = [];

        foreach ($events as $event) {
            $staffing = $event->staffing;

            if (! $staffing) {
                continue;
            }

            $occurrence    = $this->resolveTargetOccurrence($event);
            $startDate     = $occurrence ? Carbon::parse($occurrence->start_time) : now();
            $endDate       = $occurrence ? Carbon::parse($occurrence->end_time) : now();
            $sectionTitles = $this->buildSectionTitles($staffing->sections);

            $result[] = [
                'id'         => $staffing->id,
                'channel_id' => (int) $staffing->discord_channel_id,
                'message_id' => $staffing->discord_message_id,
                'event_id'   => $event->id,
                ...$sectionTitles,
                'event' => [
                    'id'         => $event->id,
                    'title'      => $event->title,
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date'   => $endDate->format('Y-m-d H:i:s'),
                ],
            ];
        }

        return $result;
    }

    /**
     * Return a single staffing by its primary key, formatted for the bot.
     */
    public function getById(int $id): array
    {
        $staffing = Staffing::with([
            'event.calendar',
            'event.occurrences' => fn($q) => $q
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time'),
            'sections.positions.bookings.bookedBy',
        ])->findOrFail($id);
        $event    = $staffing->event;

        $occurrence    = $this->resolveTargetOccurrence($event);
        $startDate     = $occurrence ? Carbon::parse($occurrence->start_time) : now();
        $endDate       = $occurrence ? Carbon::parse($occurrence->end_time) : now();
        $sectionTitles = $this->buildSectionTitles($staffing->sections);

        $allPositions = [];

        foreach ($staffing->sections as $index => $section) {
            $sectionNumber = $index + 1;

            foreach ($section->positions as $position) {
                $startTime = $position->start_time ? substr($position->start_time, 0, 5) : null;
                $endTime   = $position->end_time ? substr($position->end_time, 0, 5) : null;
                $booking   = $position->bookings->first();

                $allPositions[] = [
                    'id'            => $position->id,
                    'callsign'      => $position->position_id,
                    'booking_id'    => $booking?->booked_by_user_id,
                    'discord_user'  => $booking?->discord_user_id,
                    'section'       => $sectionNumber,
                    'local_booking' => $position->is_local_booking ? 1 : 0,
                    'start_time'    => $startTime,
                    'end_time'      => $endTime,
                    'staffing_id'   => $section->id,
                    'name'          => $position->position_name,
                    'booked'        => $booking !== null,
                    'user'          => $booking?->bookedBy ? [
                        'id'   => $booking->bookedBy->id,
                        'name' => $booking->bookedBy->name,
                    ] : ($booking?->vatsim_cid ? [
                        'id'   => $booking->vatsim_cid,
                        'name' => "CID {$booking->vatsim_cid}",
                    ] : null),
                ];
            }
        }

        return [
            'id'          => $staffing->id,
            'description' => $event->short_description,
            'channel_id'  => $staffing->discord_channel_id,
            'message_id'  => $staffing->discord_message_id,
            ...$sectionTitles,
            'event_id'    => $event->id,
            'created_at'  => $staffing->created_at->toIso8601String(),
            'updated_at'  => $staffing->updated_at->toIso8601String(),
            'positions'   => $allPositions,
            'event' => [
                'id'                => $event->id,
                'calendar_id'       => $event->calendar_id,
                'title'             => $event->title,
                'short_description' => $event->short_description,
                'long_description'  => $event->long_description,
                'start_date'        => $startDate->format('Y-m-d H:i:s'),
                'end_date'          => $endDate->format('Y-m-d H:i:s'),
                'published'         => 1,
                'image'             => $event->banner_path,
                'user_id'           => $event->created_by,
                'created_at'        => $event->created_at->toIso8601String(),
                'updated_at'        => $event->updated_at->toIso8601String(),
            ],
        ];
    }

    /**
     * Find a staffing by Discord message ID and return its full formatted data.
     */
    public function getByMessageId(string $messageId): array
    {
        $staffing = Staffing::where('discord_message_id', $messageId)->first();

        if (! $staffing) {
            abort(404, 'Staffing not found');
        }

        return $this->getById($staffing->id);
    }

    /**
     * Return the staffing detail for a given event ID, formatted for the legacy
     * GET /api/events/{id}/staffing endpoint.
     */
    public function getForEvent(int $eventId): array
    {
        $event    = Event::with(['calendar', 'staffing.sections.positions.bookings.bookedBy'])->findOrFail($eventId);
        $staffing = $event->staffing;

        if (! $staffing) {
            abort(404, 'No staffing configured for this event');
        }

        $occurrence   = $this->resolveTargetOccurrence($event);
        $targetDate   = $occurrence ? Carbon::parse($occurrence->start_time)->format('Y-m-d') : now()->format('Y-m-d');

        return [
            'event_id'           => $event->id,
            'title'              => $event->title,
            'channel_id'         => $staffing->discord_channel_id,
            'message_id'         => $staffing->discord_message_id,
            'discord_channel_id' => $staffing->discord_channel_id,
            'discord_message_id' => $staffing->discord_message_id,
            'staffing'           => $staffing->sections->map(function ($section) use ($targetDate) {
                return [
                    'id'        => $section->id,
                    'name'      => $section->title,
                    'positions' => $section->positions->map(function ($position) use ($targetDate) {
                        $startDatetime = $position->start_time
                            ? Carbon::parse($targetDate . ' ' . $position->start_time)
                            : null;
                        $endDatetime = $position->end_time
                            ? Carbon::parse($targetDate . ' ' . $position->end_time)
                            : null;

                        $booking = $position->bookings->sortByDesc('created_at')->first();

                        return [
                            'id'         => $position->id,
                            'callsign'   => $position->position_id,
                            'name'       => $position->position_name,
                            'start_time' => $startDatetime?->format('H:i'),
                            'end_time'   => $endDatetime?->format('H:i'),
                            'booked'     => $booking !== null,
                            'user'       => $booking?->bookedBy ? [
                                'id'   => $booking->bookedBy->id,
                                'name' => $booking->bookedBy->name,
                            ] : ($booking?->vatsim_cid ? [
                                'id'   => $booking->vatsim_cid,
                                'name' => "CID {$booking->vatsim_cid}",
                            ] : null),
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    /**
     * Persist a Discord message ID onto an existing staffing record.
     */
    public function updateMessageId(int $staffingId, string $messageId): void
    {
        $staffing = Staffing::findOrFail($staffingId);
        $staffing->update(['discord_message_id' => $messageId]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveTargetOccurrence(Event $event): ?EventOccurrence
    {
        if ($event->relationLoaded('occurrences')) {
            return $event->occurrences
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->sortBy('start_time')
                ->first();
        }

        return $event->occurrences()
            ->where('start_time', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->first();
    }

    private function buildSectionTitles($sections): array
    {
        $sectionTitles = [];
        $index         = 0;

        foreach ($sections as $section) {
            $index++;
            $sectionTitles["section_{$index}_title"] = $section->title;
        }

        if ($index > 4) {
            Log::warning("Staffing has {$index} sections; the bot protocol only recognises section_1_title through section_4_title. Sections beyond index 4 will be ignored by the bot.");
        }

        // Fill missing keys up to 4 so the bot always receives all four fields
        for ($i = $index + 1; $i <= 4; $i++) {
            $sectionTitles["section_{$i}_title"] = null;
        }

        return $sectionTitles;
    }
}
