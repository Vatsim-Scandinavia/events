<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ControlCenterService;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\EventResource;
use App\Http\Resources\StaffingResource;

/**
 * API Controller
 */
class ApiController extends Controller
{
    public function __construct(
        protected ControlCenterService $controlCenterService,
        protected RecurringEventService $recurringEventService
    )
    {
    }

    /**
     * Get all events
     */
    public function events(Request $request)
    {
        $query = Event::with(['calendar', 'staffings.positions.bookedBy']);

        if ($request->boolean('upcoming', true)) {
            $query->where('end_datetime', '>=', now());
        }

        if ($request->boolean('staffing', false)) {
            $query->whereHas('staffings');
        }

        $events = $query->orderBy('start_datetime')->get();

        return EventResource::collection($events);
    }

    /**
     * Get single event by ID
     */
    public function event($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy'])->findOrFail($id);

        return new EventResource($event);
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
     * Get event staffing
     */
    public function staffing($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy', 'staffings.event'])->findOrFail($id);
        $targetOccurrenceDate = $this->calculateTargetOccurrence($event);

        return new StaffingResource($event, $targetOccurrenceDate);
    }

    /**
     * Get all staffings
     */
    public function getAllStaffings()
    {
        $events = Event::whereNotNull('discord_staffing_channel_id')
            ->whereNotNull('discord_staffing_message_id')
            ->with(['staffings.positions.bookedBy', 'staffings.event'])
            ->get();

        $staffingResources = $events->map(fn($event) => 
            new StaffingResource($event, $this->calculateTargetOccurrence($event))
        );

        return StaffingResource::collection($staffingResources);
    }

    /**
     * Get staffing by message_id
     */
    public function getStaffingByMessageId(Request $request)
    {
        $messageId = $request->query('message_id');
        
        if (!$messageId) {
            return response()->json(['error' => 'message_id parameter required'], 400);
        }

        $event = Event::where('discord_staffing_message_id', $messageId)->first();
        
        if (!$event) {
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        $staffing = $event->staffings()->with(['positions.bookedBy'])->orderBy('order')->first();
        
        if (!$staffing) {
            return response()->json(['error' => 'No staffing sections found'], 404);
        }

        return $this->getStaffing($staffing->id);
    }

    /**
     * Get staffing by ID
     */
    public function getStaffing($id)
    {
        $staffing = \App\Models\Staffing::with(['event', 'positions.bookedBy'])->findOrFail($id);
        $event = $staffing->event->load(['staffings.positions.bookedBy', 'staffings.event']);
        
        $targetOccurrenceDate = $this->calculateTargetOccurrence($event);
        
        return new StaffingResource($event, $targetOccurrenceDate);
    }

    /**
     * Update staffing
     */
    public function updateStaffing(Request $request, $id)
    {
        $validated = $request->validate([
            'message_id' => 'required|string',
        ]);

        $staffing = \App\Models\Staffing::with('event')->findOrFail($id);
        
        $staffing->event->update([
            'discord_staffing_message_id' => $validated['message_id'],
        ]);

        return response()->json([
            'message' => 'Staffing updated successfully',
        ], 200);
    }

    /**
     * Book a position
     */
    public function book(Request $request)
    {
        $validated = $request->validate([
            'cid' => 'required|integer',
            'discord_user_id' => 'required',
            'position' => 'required|string',
            'message_id' => 'required',
            'section' => 'nullable|integer',
        ]);

        $event = \App\Models\Event::where('discord_staffing_message_id', $validated['message_id'])->first();
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
     * Find a position by callsign and optional section
     */
    protected function findPosition($event, string $callsign, ?int $section): ?\App\Models\StaffingPosition
    {
        $query = \App\Models\StaffingPosition::whereHas('staffing', function($q) use ($event) {
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
     * Unbook a position
     */
    public function unbook(Request $request)
    {
        $validated = $request->validate([
            'discord_user_id' => 'required',
            'message_id' => 'required',
            'position' => 'nullable|string',
            'section' => 'nullable|integer',
        ]);

        $event = \App\Models\Event::where('discord_staffing_message_id', $validated['message_id'])->first();
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
     * Find booked positions matching criteria
     */
    protected function findBookedPositions($event, array $criteria)
    {
        $query = \App\Models\StaffingPosition::whereHas('staffing', function($q) use ($event) {
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

    /**
     * Setup staffing
     */
    public function setup(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:staffings,id',
        ]);

        $staffing = \App\Models\Staffing::with(['event', 'positions'])->findOrFail($validated['id']);
        $event = $staffing->event;

        if (!$event->discord_staffing_channel_id) {
            return response()->json(['error' => 'No Discord channel configured for this event'], 400);
        }

        $notificationService = app(\App\Services\DiscordBotNotificationService::class);
        $notificationService->notifyStaffingChanged($event, 'setup');

        return response()->json([
            'message' => 'Staffing setup initiated',
        ], 200);
    }

    /**
     * Reset all bookings for a staffing
     */
    public function resetStaffing($id)
    {
        $staffing = \App\Models\Staffing::with(['event', 'positions'])->findOrFail($id);
        $event = $staffing->event;

        if (!$event->isRecurring()) {
            return response()->json(['error' => 'Staffing reset is only available for recurring events'], 400);
        }

        $bookedPositions = $event->staffings()
            ->with('positions')
            ->get()
            ->flatMap(function ($staffing) {
                return $staffing->positions;
            })
            ->filter(function ($position) {
                return $position->isBooked();
            });

        foreach ($bookedPositions as $position) {
            if ($position->control_center_booking_id) {
                try {
                    $this->controlCenterService->deleteBooking($position->control_center_booking_id);
                } catch (\Exception $e) {
                    \Log::warning('Failed to delete Control Center booking during API reset', [
                        'booking_id' => $position->control_center_booking_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $position->update([
                'booked_by_user_id' => null,
                'discord_user_id' => null,
                'vatsim_cid' => null,
                'control_center_booking_id' => null,
            ]);
        }

        $notificationService = app(\App\Services\DiscordBotNotificationService::class);
        try {
            $notificationService->notifyStaffingChanged($event, 'updated');
        } catch (\Exception $e) {
            \Log::warning('Failed to update Discord message after API reset', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'All staffing positions have been reset successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'channel_id' => $event->discord_staffing_channel_id,
            ],
        ], 200);
    }

}
