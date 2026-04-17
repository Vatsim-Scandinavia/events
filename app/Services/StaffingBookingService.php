<?php

namespace App\Services;

use App\Jobs\UpdateDiscordStaffingMessage;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Staffing;
use App\Models\StaffingBooking;
use App\Models\StaffingPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StaffingBookingService
{
    public function __construct(
        protected ControlCenterService $controlCenterService,
    ) {}

    /**
     * Book a staffing position for a Discord user.
     *
     * @param array $data Keys: cid, discord_user_id, position, message_id, section (nullable)
     */
    public function book(array $data): void
    {
        $staffing = Staffing::where('discord_message_id', $data['message_id'])
            ->with('event')
            ->first();

        if (! $staffing) {
            abort(404, 'Staffing not found');
        }

        $event      = $staffing->event;
        $occurrence = $this->resolveTargetOccurrence($event);
        $position   = $this->resolvePosition($staffing, $data['position'], $data['section'] ?? null, $occurrence);

        if ($position->isBooked($occurrence)) {
            abort(422, 'Position already booked');
        }

        // Prevent the same user from holding multiple positions in the same occurrence
        $alreadyBooked = StaffingBooking::where('occurrence_id', $occurrence->id)
            ->where('discord_user_id', $data['discord_user_id'])
            ->whereHas('position', fn($q) => $q->whereHas('section', fn($q2) => $q2->where('staffing_id', $staffing->id)))
            ->exists();

        if ($alreadyBooked) {
            abort(422, 'You already hold a position in this staffing. Unbook first.');
        }

        if ($position->start_time && $position->end_time) {
            $startDatetime = Carbon::parse($occurrence->start_time->format('Y-m-d') . ' ' . $position->start_time);
            $endDatetime   = Carbon::parse($occurrence->start_time->format('Y-m-d') . ' ' . $position->end_time);
        } else {
            $startDatetime = $occurrence->start_time;
            $endDatetime   = $occurrence->end_time;
        }

        $ccBookingId = $this->controlCenterService->createBooking([
            'cid'      => $data['cid'],
            'date'     => $startDatetime->format('d/m/Y'),
            'position' => $position->position_id,
            'start_at' => $startDatetime->format('H:i'),
            'end_at'   => $endDatetime->format('H:i'),
            'tag'      => config('services.control_center.booking_tag'),
            'source'   => 'Discord',
        ]);

        StaffingBooking::create([
            'position_id'               => $position->id,
            'occurrence_id'             => $occurrence->id,
            'vatsim_cid'                => $data['cid'],
            'discord_user_id'           => $data['discord_user_id'],
            'control_center_booking_id' => $ccBookingId,
        ]);

        UpdateDiscordStaffingMessage::dispatch($event->id, 'update');
    }

    /**
     * Unbook all matching positions for a Discord user.
     *
     * @param array $data Keys: discord_user_id, message_id, position (nullable), section (nullable)
     */
    public function unbook(array $data): void
    {
        $staffing = Staffing::where('discord_message_id', $data['message_id'])
            ->with('event')
            ->first();

        if (! $staffing) {
            abort(404, 'Staffing not found');
        }

        $event = $staffing->event;

        $query = StaffingBooking::where('discord_user_id', $data['discord_user_id'])
            ->whereHas('position', function ($q) use ($staffing) {
                $q->whereHas('section', fn($q2) => $q2->where('staffing_id', $staffing->id));
            });

        if (! empty($data['position'])) {
            $query->whereHas('position', fn($q) => $q->where('position_id', $data['position']));
        }

        if (! empty($data['section'])) {
            $sections      = $staffing->sections()->orderBy('order')->get()->values();
            $targetSection = $sections->get($data['section'] - 1);
            if ($targetSection) {
                $query->whereHas('position', fn($q) => $q->where('section_id', $targetSection->id));
            }
        }

        $bookings = $query->with('position')->get();

        if ($bookings->isEmpty()) {
            abort(404, 'No matching booking found');
        }

        foreach ($bookings as $booking) {
            if ($booking->control_center_booking_id) {
                $this->controlCenterService->deleteBooking($booking->control_center_booking_id);
            }
            $booking->delete();
        }

        UpdateDiscordStaffingMessage::dispatch($event->id, 'update');
    }

    /**
     * Reset all bookings for a staffing (recurring events only).
     *
     * @return array Event summary for the API response.
     */
    public function reset(int $staffingId): array
    {
        $staffing = Staffing::with(['event', 'sections.positions.bookings'])->findOrFail($staffingId);
        $event    = $staffing->event;

        if (! $event->isRecurring()) {
            abort(400, 'Staffing reset is only available for recurring events');
        }

        foreach ($staffing->sections as $section) {
            foreach ($section->positions as $position) {
                foreach ($position->bookings as $booking) {
                    if ($booking->control_center_booking_id) {
                        $this->controlCenterService->deleteBooking($booking->control_center_booking_id);
                    }
                    $booking->delete();
                }
            }
        }

        try {
            UpdateDiscordStaffingMessage::dispatch($event->id, 'update', true);
        } catch (\Exception $e) {
            Log::warning('Failed to update Discord message after staffing reset', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return [
            'id'         => $event->id,
            'title'      => $event->title,
            'channel_id' => $staffing->discord_channel_id,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveTargetOccurrence(Event $event): EventOccurrence
    {
        $occurrence = $event->occurrences()
            ->where('start_time', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->first();

        return $occurrence ?? $event->occurrences()->orderByDesc('start_time')->firstOrFail();
    }

    private function resolvePosition(Staffing $staffing, string $positionId, ?int $sectionNumber, ?EventOccurrence $occurrence = null): StaffingPosition
    {
        // Eager-load bookings scoped to the target occurrence so that isBooked()
        // can use the loaded collection instead of firing an extra query.
        $withBookings = fn($q) => $occurrence
            ? $q->where('occurrence_id', $occurrence->id)
            : $q;

        if ($sectionNumber !== null) {
            $sections      = $staffing->sections()->orderBy('order')->get()->values();
            $targetSection = $sections->get($sectionNumber - 1);

            if (! $targetSection) {
                abort(404, 'Section not found');
            }

            $position = $targetSection->positions()
                ->with(['bookings' => $withBookings])
                ->where('position_id', $positionId)
                ->first();
        } else {
            $position = StaffingPosition::with(['bookings' => $withBookings])
                ->whereHas('section', fn($q) => $q->where('staffing_id', $staffing->id))
                ->where('position_id', $positionId)
                ->first();
        }

        if (! $position) {
            abort(404, 'Position not found');
        }

        return $position;
    }
}
