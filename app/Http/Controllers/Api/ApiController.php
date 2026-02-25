<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ControlCenterService;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * API Controller matching the old events system endpoints
 * for backward compatibility with the Python Discord bot
 */
class ApiController extends Controller
{
    public function __construct(
        protected ControlCenterService $controlCenterService,
        protected RecurringEventService $recurringEventService
    ) {}

    /**
     * Get all events (with optional staffing filter)
     * Matches: GET /api/events
     */
    public function events(Request $request)
    {
        $query = Event::with(['calendar', 'staffings.positions.bookedBy']);

        // Filter by upcoming only
        if ($request->boolean('upcoming', true)) {
            $query->where('end_datetime', '>=', now());
        }

        // Filter events with staffing only
        if ($request->boolean('staffing', false)) {
            $query->whereHas('staffings');
        }

        $events = $query->orderBy('start_datetime')->get();

        return response()->json(
            $events->map(fn($event) => $this->formatEvent($event))
        );
    }

    /**
     * Get single event by ID
     * Matches: GET /api/events/{id}
     */
    public function event($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy'])->findOrFail($id);

        return response()->json($this->formatEvent($event));
    }

    /**
     * Get event staffing
     * Matches: GET /api/events/{id}/staffing
     */
    public function staffing($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy'])->findOrFail($id);

        $targetOccurrenceDate = $this->resolveTargetOccurrenceDate($event);

        return response()->json([
            'event_id' => $event->id,
            'title' => $event->title,
            'channel_id' => $event->discord_staffing_channel_id,
            'message_id' => $event->discord_staffing_message_id,
            'discord_channel_id' => $event->discord_staffing_channel_id,
            'discord_message_id' => $event->discord_staffing_message_id,
            'staffing' => $event->staffings->map(function ($staffing) use ($targetOccurrenceDate) {
                return [
                    'id' => $staffing->id,
                    'name' => $staffing->name,
                    'positions' => $staffing->positions->sortBy('order')->map(function ($position) use ($targetOccurrenceDate) {
                        $startDatetime = null;
                        $endDatetime = null;

                        if ($position->start_time) {
                            $startDatetime = Carbon::parse($targetOccurrenceDate->format('Y-m-d') . ' ' . $position->start_time);
                        }
                        if ($position->end_time) {
                            $endDatetime = Carbon::parse($targetOccurrenceDate->format('Y-m-d') . ' ' . $position->end_time);
                        }

                        return [
                            'id' => $position->id,
                            'callsign' => $position->position_id,
                            'name' => $position->position_name,
                            'start_time' => $startDatetime ? $startDatetime->format('H:i') : null,
                            'end_time' => $endDatetime ? $endDatetime->format('H:i') : null,
                            'booked' => $position->isBooked(),
                            'user' => $position->bookedBy ? [
                                'id' => $position->bookedBy->id,
                                'name' => $position->bookedBy->name,
                            ] : ($position->vatsim_cid ? [
                                'id' => $position->vatsim_cid,
                                'name' => "CID {$position->vatsim_cid}",
                            ] : null),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Get all staffings (for bot to match by channel_id)
     * Matches: GET /api/staffings
     */
    public function getAllStaffings()
    {
        $events = Event::whereNotNull('discord_staffing_channel_id')
            ->whereNotNull('discord_staffing_message_id')
            ->with(['staffings.positions.bookedBy'])
            ->get();

        $result = [];
        foreach ($events as $event) {
            $firstStaffing = $event->staffings()->with('positions.bookedBy')->orderBy('order')->first();
            if (!$firstStaffing) continue;

            $targetOccurrenceDate = $this->resolveTargetOccurrenceDate($event);

            $allStaffings = $event->staffings()->with('positions.bookedBy')->orderBy('order')->get();

            $sectionTitles = $this->buildSectionTitles($allStaffings);

            $result[] = [
                'id'         => $firstStaffing->id,
                'channel_id' => (int) $event->discord_staffing_channel_id,
                'message_id' => $event->discord_staffing_message_id,
                'event_id'   => $event->id,
                ...$sectionTitles,
                'event' => [
                    'id'         => $event->id,
                    'title'      => $event->title,
                    'start_date' => $targetOccurrenceDate->format('Y-m-d H:i:s'),
                    'end_date'   => $targetOccurrenceDate->copy()
                        ->setTimeFrom($event->end_datetime)
                        ->format('Y-m-d H:i:s'),
                ],
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Get staffing by message_id (for bot when handling button clicks)
     * Matches: GET /api/staffings?message_id={id}
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
     * Get staffing by ID (for bot)
     * Matches: GET /api/staffings/{id}
     */
    public function getStaffing($id)
    {
        $staffing = \App\Models\Staffing::with(['event.calendar', 'positions.bookedBy'])->findOrFail($id);
        $event = $staffing->event;

        $targetOccurrenceDate = $this->resolveTargetOccurrenceDate($event);

        $allStaffings = $event->staffings()->with('positions.bookedBy')->orderBy('order')->get();

        $sectionTitles = $this->buildSectionTitles($allStaffings);

        $allPositions = [];
        foreach ($allStaffings as $index => $section) {
            $sectionNumber = $index + 1;
            foreach ($section->positions->sortBy('order') as $position) {
                $startTime = $position->start_time ? substr($position->start_time, 0, 5) : null;
                $endTime = $position->end_time ? substr($position->end_time, 0, 5) : null;

                $allPositions[] = [
                    'id' => $position->id,
                    'callsign' => $position->position_id,
                    'booking_id' => $position->booked_by_user_id,
                    'discord_user' => $position->discord_user_id,
                    'section' => $sectionNumber,
                    'local_booking' => $position->is_local ? 1 : 0,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'staffing_id' => $section->id,
                    'name' => $position->position_name,
                    'booked' => $position->isBooked(),
                    'user' => $position->bookedBy ? [
                        'id' => $position->bookedBy->id,
                        'name' => $position->bookedBy->name,
                    ] : ($position->vatsim_cid ? [
                        'id' => $position->vatsim_cid,
                        'name' => "CID {$position->vatsim_cid}",
                    ] : null),
                ];
            }
        }

        return response()->json([
            'data' => [
                'id' => $staffing->id,
                'description' => $event->staffing_description ?? $event->short_description,
                'channel_id' => $event->discord_staffing_channel_id,
                'message_id' => $event->discord_staffing_message_id,
                ...$sectionTitles,
                'event_id' => $event->id,
                'created_at' => $staffing->created_at->toIso8601String(),
                'updated_at' => $staffing->updated_at->toIso8601String(),
                'positions' => $allPositions,
                'event' => [
                    'id'                => $event->id,
                    'calendar_id'       => $event->calendar_id,
                    'title'             => $event->title,
                    'short_description' => $event->short_description,
                    'long_description'  => $event->long_description,
                    'start_date'        => $targetOccurrenceDate->format('Y-m-d H:i:s'),
                    'end_date'          => $targetOccurrenceDate->copy()
                        ->setTimeFrom($event->end_datetime)
                        ->format('Y-m-d H:i:s'),
                    'published'         => 1,
                    'image'             => $event->banner_path,
                    'user_id'           => $event->created_by,
                    'created_at'        => $event->created_at->toIso8601String(),
                    'updated_at'        => $event->updated_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Update staffing (for bot to save message_id after posting)
     * Matches: PATCH /api/staffings/{id}/update
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
     * Matches old format: POST /api/staffing with body
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

        \Log::info('Book request received', $validated);

        $event = \App\Models\Event::where('discord_staffing_message_id', $validated['message_id'])->first();

        if (!$event) {
            \Log::error('Event not found for message_id: ' . $validated['message_id']);
            return response()->json(['error' => 'Staffing not found'], 404);
        }

        \Log::info('Found event', ['event_id' => $event->id, 'title' => $event->title]);

        $query = \App\Models\StaffingPosition::whereHas('staffing', function ($q) use ($event) {
            $q->where('event_id', $event->id);
        })->where('position_id', $validated['position']);

        if (isset($validated['section'])) {
            \Log::info('Section specified', ['section' => $validated['section']]);

            $staffings = $event->staffings()->orderBy('order')->get();

            \Log::info('Found staffings', ['count' => $staffings->count()]);

            if (isset($staffings[$validated['section'] - 1])) {
                $targetStaffing = $staffings[$validated['section'] - 1];
                \Log::info('Target staffing', ['id' => $targetStaffing->id, 'name' => $targetStaffing->name]);
                $query->where('staffing_id', $targetStaffing->id);
            } else {
                \Log::error('Section not found', ['section' => $validated['section'], 'available_count' => $staffings->count()]);
                return response()->json(['error' => 'Section not found'], 404);
            }
        }

        \Log::info('Query SQL', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

        $position = $query->first();

        if ($position) {
            \Log::info('Position found!', ['position_id' => $position->id]);
        }

        if (!$position) {
            \Log::error('Position not found', ['callsign' => $validated['position'], 'section' => $validated['section'] ?? 'none']);
            return response()->json(['error' => 'Position not found'], 404);
        }

        if ($position->isBooked()) {
            return response()->json(['error' => 'Position already booked'], 422);
        }

        $position->update([
            'vatsim_cid' => $validated['cid'],
            'discord_user_id' => $validated['discord_user_id'],
        ]);

        $targetOccurrenceDate = $this->resolveTargetOccurrenceDate($event);

        if ($position->start_time && $position->end_time) {
            $startDatetime = Carbon::parse($targetOccurrenceDate->format('Y-m-d') . ' ' . $position->start_time);
            $endDatetime = Carbon::parse($targetOccurrenceDate->format('Y-m-d') . ' ' . $position->end_time);
        } else {
            $startDatetime = $targetOccurrenceDate;
            $endDatetime = $targetOccurrenceDate->copy()->setTimeFrom($event->end_datetime);
        }

        $bookingData = [
            'cid' => $validated['cid'],
            'date' => $startDatetime->format('d/m/Y'),
            'position' => $position->position_id,
            'start_at' => $startDatetime->format('H:i'),
            'end_at' => $endDatetime->format('H:i'),
            'tag' => 3,
            'source' => 'Discord',
        ];

        \Log::info('Booking data prepared for Control Center', [
            'booking_data' => $bookingData,
            'position_start_time' => $position->start_time ?? 'null',
            'position_end_time' => $position->end_time ?? 'null',
            'calculated_start_datetime' => $startDatetime->toDateTimeString(),
            'calculated_end_datetime' => $endDatetime->toDateTimeString(),
            'event_title' => $event->title,
            'event_is_recurring' => $event->isRecurring(),
        ]);

        $bookingId = $this->controlCenterService->createBooking($bookingData);

        if ($bookingId) {
            $position->update(['control_center_booking_id' => $bookingId]);
        }

        \App\Jobs\UpdateDiscordStaffingMessage::dispatch($event->id, 'updated');

        return response()->json([
            'message' => 'Position booked successfully',
        ], 200);
    }

    /**
     * Unbook a position
     * Matches old format: DELETE /api/staffing with body
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

        $query = \App\Models\StaffingPosition::whereHas('staffing', function ($q) use ($event) {
            $q->where('event_id', $event->id);
        })->where(function ($q) {
            $q->whereNotNull('booked_by_user_id')
                ->orWhereNotNull('vatsim_cid');
        });

        if (isset($validated['position'])) {
            $query->where('position_id', $validated['position']);
        }

        if (isset($validated['section'])) {
            $staffings = $event->staffings()->orderBy('order')->get();

            if (isset($staffings[$validated['section'] - 1])) {
                $targetStaffing = $staffings[$validated['section'] - 1];
                $query->where('staffing_id', $targetStaffing->id);
            }
        }

        if (isset($validated['discord_user_id'])) {
            $query->where('discord_user_id', $validated['discord_user_id']);
        }

        $positions = $query->get();

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

        return response()->json([
            'message' => 'Position unbooked successfully',
        ], 200);
    }

    /**
     * Setup staffing - tells Discord bot to post staffing message
     * Matches old format: POST /api/staffing/setup
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
     * Matches bot format: POST /api/staffings/{id}/reset
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
            ->flatMap(fn($staffing) => $staffing->positions)
            ->filter(fn($position) => $position->isBooked());

        foreach ($bookedPositions as $position) {
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

    /**
     * Resolve the target occurrence date for an event.
     * For recurring events, returns the next future occurrence start datetime.
     * Falls back to the event's own start_datetime if no future occurrence is found.
     */
    protected function resolveTargetOccurrenceDate(Event $event): Carbon
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
     * Build the section_1_title … section_4_title map from an ordered staffing collection.
     */
    protected function buildSectionTitles($allStaffings): array
    {
        $sectionTitles = [];

        foreach ($allStaffings as $index => $section) {
            $sectionNumber = $index + 1;
            if ($sectionNumber <= 4) {
                $sectionTitles["section_{$sectionNumber}_title"] = $section->name;
            }
        }

        for ($i = count($allStaffings) + 1; $i <= 4; $i++) {
            $sectionTitles["section_{$i}_title"] = null;
        }

        return $sectionTitles;
    }

    /**
     * Format event for API response (backward compatible)
     */
    protected function formatEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->title,
            'short_description' => $event->short_description,
            'description' => $event->long_description,
            'date' => $event->start_datetime->format('Y-m-d'),
            'start_time' => $event->start_datetime->format('H:i'),
            'end_time' => $event->end_datetime->format('H:i'),
            'start_datetime' => $event->start_datetime->toIso8601String(),
            'end_datetime' => $event->end_datetime->toIso8601String(),
            'airports' => $event->featured_airports ?? [],
            'banner' => $event->banner_path ? asset('storage/' . $event->banner_path) : null,
            'url' => url('/events/' . $event->id),
            'discord_channel_id' => $event->discord_staffing_channel_id,
            'discord_message_id' => $event->discord_staffing_message_id,
            'has_staffing' => $event->staffings->isNotEmpty(),
        ];
    }
}
