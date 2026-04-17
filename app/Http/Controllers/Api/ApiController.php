<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateDiscordStaffingMessage;
use App\Services\BotEventService;
use App\Services\BotStaffingService;
use App\Services\StaffingBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP controller matching the old events system API endpoints
 * for backward compatibility with the Python Discord bot.
 *
 * All business logic lives in BotEventService, BotStaffingService,
 * and StaffingBookingService.
 */
class ApiController extends Controller
{
    public function __construct(
        protected BotEventService $eventService,
        protected BotStaffingService $staffingService,
        protected StaffingBookingService $bookingService,
    ) {}

    // -------------------------------------------------------------------------
    // Event endpoints
    // -------------------------------------------------------------------------

    /** GET /api/events */
    public function events(Request $request): JsonResponse
    {
        $events = $this->eventService->getAll(
            upcoming: $request->boolean('upcoming', true),
            staffingOnly: $request->boolean('staffing', false),
        );

        return response()->json($events->map(fn($e) => $this->eventService->format($e)));
    }

    /** GET /api/events/{id} */
    public function event(int $id): JsonResponse
    {
        return response()->json($this->eventService->format($this->eventService->getById($id)));
    }

    /** GET /api/events/{id}/staffing */
    public function staffing(int $id): JsonResponse
    {
        return response()->json($this->staffingService->getForEvent($id));
    }

    // -------------------------------------------------------------------------
    // Staffing endpoints
    // -------------------------------------------------------------------------

    /** GET /api/staffings  (optionally ?message_id=xxx) */
    public function getAllStaffings(): JsonResponse
    {
        return response()->json(['data' => $this->staffingService->getAll()]);
    }

    /** GET /api/staffings?message_id={id} */
    public function getStaffingByMessageId(Request $request): JsonResponse
    {
        $messageId = $request->query('message_id');

        if (! $messageId) {
            return response()->json(['error' => 'message_id parameter required'], 400);
        }

        return response()->json(['data' => $this->staffingService->getByMessageId($messageId)]);
    }

    /** GET /api/staffings/{id} */
    public function getStaffing(int $id): JsonResponse
    {
        return response()->json(['data' => $this->staffingService->getById($id)]);
    }

    /** PATCH /api/staffings/{id}/update */
    public function updateStaffing(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['message_id' => 'required|string']);

        $this->staffingService->updateMessageId($id, $validated['message_id']);

        return response()->json(['message' => 'Staffing updated successfully']);
    }

    // -------------------------------------------------------------------------
    // Booking endpoints
    // -------------------------------------------------------------------------

    /** POST /api/staffing  |  POST /api/staffings/book */
    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cid'             => 'required|integer',
            'discord_user_id' => 'required',
            'position'        => 'required|string',
            'message_id'      => 'required',
            'section'         => 'nullable|integer',
        ]);

        $this->bookingService->book($validated);

        return response()->json(['message' => 'Position booked successfully']);
    }

    /** DELETE /api/staffing  |  DELETE /api/staffings/unbook  |  POST /api/staffings/unbook */
    public function unbook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discord_user_id' => 'required',
            'message_id'      => 'required',
            'position'        => 'nullable|string',
            'section'         => 'nullable|integer',
        ]);

        $this->bookingService->unbook($validated);

        return response()->json(['message' => 'Position unbooked successfully']);
    }

    /** POST /api/staffing/setup */
    public function setup(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => 'required|exists:staffings,id']);

        $staffing = \App\Models\Staffing::with('event')->findOrFail($validated['id']);

        if (! $staffing->discord_channel_id) {
            return response()->json(['error' => 'No Discord channel configured for this event'], 400);
        }

        UpdateDiscordStaffingMessage::dispatch($staffing->event->id, 'setup');

        return response()->json(['message' => 'Staffing setup initiated']);
    }

    /** POST /api/staffings/{id}/reset */
    public function resetStaffing(int $id): JsonResponse
    {
        $eventSummary = $this->bookingService->reset($id);

        return response()->json([
            'message' => 'All staffing positions have been reset successfully',
            'event'   => $eventSummary,
        ]);
    }
}
