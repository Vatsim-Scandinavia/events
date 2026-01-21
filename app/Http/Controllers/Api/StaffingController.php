<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Staffing;
use App\Services\DiscordBotNotificationService;
use App\Services\RecurringEventService;
use Illuminate\Http\Request;
use App\Http\Resources\StaffingResource;
use Carbon\Carbon;

/**
 * Staffing API Controller
 */
class StaffingController extends Controller
{
    public function __construct(
        protected RecurringEventService $recurringEventService
    )
    {
    }

    /**
     * Get all staffings
     */
    public function index()
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
    public function getByMessageId(Request $request)
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

        return $this->show($staffing->id);
    }

    /**
     * Get staffing by section ID
     */
    public function show($id)
    {
        $staffing = Staffing::with(['event', 'positions.bookedBy'])->findOrFail($id);
        
        if (!$staffing->event) {
            return response()->json(['error' => 'Event not found for this staffing section'], 404);
        }
        
        $event = $staffing->event->load(['staffings.positions.bookedBy', 'staffings.event']);
        
        $targetOccurrenceDate = $this->calculateTargetOccurrence($event);
        
        return new StaffingResource($event, $targetOccurrenceDate);
    }

    /**
     * Get event staffing by event ID
     */
    public function getEventStaffing($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy', 'staffings.event'])->findOrFail($id);
        $targetOccurrenceDate = $this->calculateTargetOccurrence($event);

        return new StaffingResource($event, $targetOccurrenceDate);
    }

    /**
     * Update staffing message ID
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'message_id' => 'required|string',
        ]);

        $staffing = Staffing::with('event')->findOrFail($id);
        
        $staffing->event->update([
            'discord_staffing_message_id' => $validated['message_id'],
        ]);

        return response()->json([
            'message' => 'Staffing updated successfully',
        ], 200);
    }

    /**
     * Setup staffing
     */
    public function setup(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:staffings,id',
        ]);

        $staffing = Staffing::with(['event', 'positions'])->findOrFail($validated['id']);
        $event = $staffing->event;

        if (!$event->discord_staffing_channel_id) {
            return response()->json(['error' => 'No Discord channel configured for this event'], 400);
        }

        $notificationService = app(DiscordBotNotificationService::class);
        $notificationService->notifyStaffingChanged($event, 'setup');

        return response()->json([
            'message' => 'Staffing setup initiated',
        ], 200);
    }

    /**
     * Reset all bookings for a staffing
     */
    public function reset($id)
    {
        $staffing = Staffing::with(['event', 'positions'])->findOrFail($id);
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

        $controlCenterService = app(\App\Services\ControlCenterService::class);

        foreach ($bookedPositions as $position) {
            if ($position->control_center_booking_id) {
                try {
                    $controlCenterService->deleteBooking($position->control_center_booking_id);
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

        $notificationService = app(DiscordBotNotificationService::class);
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
}
