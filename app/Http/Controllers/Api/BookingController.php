<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\StaffingPosition;
use App\Services\ControlCenterService;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Booking API Controller
 */
class BookingController extends Controller
{
    public function __construct(
        protected ControlCenterService $controlCenterService,
        protected RecurringEventService $recurringEventService
    )
    {
    }

    /**
     * Book a position
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cid' => 'required|integer',
            'discord_user_id' => 'required',
            'position' => 'required|string',
            'message_id' => 'required',
            'section' => 'nullable|integer',
        ]);

        $event = Event::where('discord_staffing_message_id', $validated['message_id'])->first();
        if (!$event) {
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        $position = $this->findPosition($event, $validated['position'], $validated['section'] ?? null);
        if (!$position) {
            return response()->json(['error' => 'Position not found'], 404);
        }

        if ($position->isBooked()) {
            return response()->json(['error' => 'Position already booked'], 422);
        }

        $position->update([
            'vatsim_cid' => $validated['cid'],
            'discord_user_id' => $validated['discord_user_id'],
        ]);

        $targetOccurrenceDate = $this->calculateTargetOccurrence($event);
        [$startDatetime, $endDatetime] = $this->calculateBookingTimes($position, $event, $targetOccurrenceDate);

        $bookingData = [
            'cid' => $validated['cid'],
            'date' => $startDatetime->format('d/m/Y'),
            'position' => $position->position_id,
            'start_at' => $startDatetime->format('H:i'),
            'end_at' => $endDatetime->format('H:i'),
            'tag' => 3,
            'source' => 'Discord',
        ];

        $bookingId = $this->controlCenterService->createBooking($bookingData);
        if ($bookingId) {
            $position->update(['control_center_booking_id' => $bookingId]);
        }

        \App\Jobs\UpdateDiscordStaffingMessage::dispatch($event->id, 'updated');

        return response()->json(['message' => 'Position booked successfully'], 200);
    }

    /**
     * Unbook a position
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'discord_user_id' => 'required',
            'message_id' => 'required',
            'position' => 'nullable|string',
            'section' => 'nullable|integer',
        ]);

        $event = Event::where('discord_staffing_message_id', $validated['message_id'])->first();
        if (!$event) {
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        $positions = $this->findBookedPositions($event, $validated);
        if ($positions->isEmpty()) {
            return response()->json(['error' => 'Position not found'], 404);
        }

        foreach ($positions as $position) {
            if ($position->control_center_booking_id) {
                $this->controlCenterService->deleteBooking($position->control_center_booking_id);
            }
            
            $position->update([
                'booked_by_user_id' => null,
                'discord_user_id' => null,
                'vatsim_cid' => null,
                'control_center_booking_id' => null,
            ]);
        }

        \App\Jobs\UpdateDiscordStaffingMessage::dispatch($event->id, 'updated');

        return response()->json(['message' => 'Position unbooked successfully'], 200);
    }

    /**
     * Calculate target occurrence date for an event (handles recurring events)
     */
    protected function calculateTargetOccurrence(Event $event): Carbon
    {
        if (!$event->isRecurring()) {
            return $event->start_datetime;
        }
        
        $instances = $this->recurringEventService->generateInstances(
            $event->recurrence_rule,
            $event->start_datetime,
            now()->addMonths(3),
            10,
            $event->cancelled_occurrences ?? []
        );
        
        $nextOccurrence = collect($instances)->first(fn($instance) => $instance['start']->isFuture());
        return $nextOccurrence ? $nextOccurrence['start'] : $event->start_datetime;
    }

    /**
     * Find a position by callsign and optional section
     */
    protected function findPosition($event, string $callsign, ?int $section): ?StaffingPosition
    {
        $query = StaffingPosition::whereHas('staffing', function($q) use ($event) {
            $q->where('event_id', $event->id);
        })->where('position_id', $callsign);

        if ($section !== null) {
            $staffings = $event->staffings()->orderBy('order')->get();
            if (!isset($staffings[$section - 1])) {
                return null;
            }
            $query->where('staffing_id', $staffings[$section - 1]->id);
        }

        return $query->first();
    }

    /**
     * Calculate booking start and end datetimes
     */
    protected function calculateBookingTimes($position, $event, $targetOccurrenceDate): array
    {
        if ($position->start_time && $position->end_time) {
            $startDatetime = Carbon::parse($targetOccurrenceDate->format('Y-m-d') . ' ' . $position->start_time);
            $endDatetime = Carbon::parse($targetOccurrenceDate->format('Y-m-d') . ' ' . $position->end_time);
        } else {
            $startDatetime = $targetOccurrenceDate;
            $endDatetime = $targetOccurrenceDate->copy()->setTimeFrom($event->end_datetime);
        }

        return [$startDatetime, $endDatetime];
    }

    /**
     * Find booked positions matching criteria
     */
    protected function findBookedPositions($event, array $criteria)
    {
        $query = StaffingPosition::whereHas('staffing', function($q) use ($event) {
            $q->where('event_id', $event->id);
        })->where(function($q) {
            $q->whereNotNull('booked_by_user_id')->orWhereNotNull('vatsim_cid');
        });

        if (isset($criteria['position'])) {
            $query->where('position_id', $criteria['position']);
        }

        if (isset($criteria['section'])) {
            $staffings = $event->staffings()->orderBy('order')->get();
            if (isset($staffings[$criteria['section'] - 1])) {
                $query->where('staffing_id', $staffings[$criteria['section'] - 1]->id);
            }
        }

        if (isset($criteria['discord_user_id'])) {
            $query->where('discord_user_id', $criteria['discord_user_id']);
        }

        return $query->get();
    }
}
