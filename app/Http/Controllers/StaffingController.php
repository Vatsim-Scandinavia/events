<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Staffing;
use App\Services\DiscordBotNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffingController extends Controller
{
    public function __construct(protected DiscordBotNotificationService $discordNotificationService)
    {
    }
    /**
     * Display staffings for an event
     */
    public function index(Event $event)
    {
        $this->authorize('manage-staffings');

        // Only allow staffing for recurring events
        if (!$event->isRecurring()) {
            return redirect()->route('events.show', $event)
                ->with('error', 'Staffing is only available for recurring events.');
        }

        $event->load('calendar');

        $staffings = $event->staffings()
            ->with('positions.bookedBy')
            ->ordered()
            ->get();

        return Inertia::render('Staffings/Manage', [
            'event' => $event,
            'staffings' => $staffings,
        ]);
    }

    /**
     * Store a newly created staffing
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('manage-staffings');

        // Only allow staffing for recurring events
        if (!$event->isRecurring()) {
            return back()->withErrors(['error' => 'Staffing is only available for recurring events.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        // Calculate order if not provided
        if (!isset($validated['order'])) {
            $maxOrder = $event->staffings()->max('order') ?? -10;
            $validated['order'] = $maxOrder + 10;
        }

        $staffing = $event->staffings()->create($validated);

        \Log::info('Staffing section "' . $staffing->name . '" (' . $staffing->id . ') created by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message
        $this->updateDiscordStaffingMessage($event);

        return back()->with('success', 'Staffing section created successfully.');
    }

    /**
     * Update the specified staffing
     */
    public function update(Request $request, Staffing $staffing)
    {
        $this->authorize('manage-staffings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $staffing->update($validated);

        \Log::info('Staffing section "' . $staffing->name . '" (' . $staffing->id . ') updated by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message
        $this->updateDiscordStaffingMessage($staffing->event);

        return back()->with('success', 'Staffing section updated successfully.');
    }

    /**
     * Remove the specified staffing
     */
    public function destroy(Staffing $staffing)
    {
        $this->authorize('manage-staffings');

        $event = $staffing->event;
        $staffing->delete();

        \Log::info('Staffing section "' . $staffing->name . '" (' . $staffing->id . ') deleted by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message
        $this->updateDiscordStaffingMessage($event);

        return back()->with('success', 'Staffing section deleted successfully.');
    }

    /**
     * Reorder staffing sections
     */
    public function reorder(Request $request, Staffing $staffing)
    {
        $this->authorize('manage-staffings');

        $validated = $request->validate([
            'staffings' => 'required|array',
            'staffings.*.id' => 'required|exists:staffings,id',
            'staffings.*.order' => 'required|integer',
        ]);

        foreach ($validated['staffings'] as $item) {
            Staffing::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        // Update Discord message
        $this->updateDiscordStaffingMessage($staffing->event);

        return back()->with('success', 'Staffing sections reordered successfully.');
    }

    /**
     * Notify Discord bot about staffing changes
     */
    protected function updateDiscordStaffingMessage($event)
    {
        $this->discordNotificationService->notifyStaffingChanged($event, 'updated');
    }

    /**
     * Reset all staffing positions for an event
     */
    public function reset(Event $event)
    {
        $this->authorize('manage-staffings');

        // Only allow for recurring events
        if (!$event->isRecurring()) {
            return back()->withErrors(['error' => 'Staffing reset is only available for recurring events.']);
        }

        $controlCenterService = app(\App\Services\ControlCenterService::class);
        
        // Get all booked positions
        $bookedPositions = $event->staffings()
            ->with('positions')
            ->get()
            ->flatMap(function ($staffing) {
                return $staffing->positions;
            })
            ->filter(function ($position) {
                return $position->isBooked();
            });

        // Delete Control Center bookings and clear position bookings
        foreach ($bookedPositions as $position) {
            // Delete from Control Center if there's a booking ID
            if ($position->control_center_booking_id) {
                $controlCenterService->deleteBooking($position->control_center_booking_id);
            }

            // Clear all booking fields (position times remain unchanged - they're time-only now)
            $position->update([
                'booked_by_user_id' => null,
                'discord_user_id' => null,
                'vatsim_cid' => null,
                'control_center_booking_id' => null,
            ]);
        }
        
        \Log::info('Staffing for event "' . $event->title . '" (' . $event->id . ') reset by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message to reflect the reset
        $this->updateDiscordStaffingMessage($event);

        return back()->with('success', 'All staffing positions have been reset successfully.');
    }
}
