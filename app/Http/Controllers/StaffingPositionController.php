<?php

namespace App\Http\Controllers;

use App\Models\Staffing;
use App\Models\StaffingPosition;
use App\Services\ControlCenterService;
use App\Services\DiscordBotNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffingPositionController extends Controller
{
    public function __construct(
        protected DiscordBotNotificationService $discordNotificationService,
        protected ControlCenterService $controlCenterService
    ) {
    }
    /**
     * Store a newly created position
     */
    public function store(Request $request, Staffing $staffing)
    {
        $this->authorize('manage-staffings');

        $validated = $request->validate([
            'position_id' => 'required|string',
            'position_name' => 'required|string',
            'is_local' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'order' => 'nullable|integer',
        ]);

        // Check if position already exists in this staffing section
        $existingPosition = $staffing->positions()
            ->where('position_id', $validated['position_id'])
            ->first();
            
        if ($existingPosition) {
            return back()->withErrors([
                'position_id' => 'This position already exists in this section.'
            ])->withInput();
        }

        // Convert empty strings to null for database storage
        if (isset($validated['start_time']) && $validated['start_time'] === '') {
            $validated['start_time'] = null;
        }
        if (isset($validated['end_time']) && $validated['end_time'] === '') {
            $validated['end_time'] = null;
        }

        // Calculate order if not provided
        if (!isset($validated['order'])) {
            $maxOrder = $staffing->positions()->max('order') ?? -10;
            $validated['order'] = $maxOrder + 10;
        }

        // Default is_local to false if not provided
        if (!isset($validated['is_local'])) {
            $validated['is_local'] = false;
        }

        $position = $staffing->positions()->create($validated);
        
        \Log::info('Position "' . $position->position_name . '" (' . $position->id . ') created by user: ' . auth()->user()->vatsim_cid);

        // Update or post Discord message
        $this->updateDiscordStaffingMessage($staffing->event);

        return back()->with('success', 'Position added successfully.');
    }

    /**
     * Update the specified position
     */
    public function update(Request $request, StaffingPosition $position)
    {
        $this->authorize('manage-staffings');

        $validated = $request->validate([
            'position_id' => 'required|string',
            'position_name' => 'required|string',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        // Check if position_id is changing to one that already exists in this staffing section
        if ($validated['position_id'] !== $position->position_id) {
            $existingPosition = $position->staffing->positions()
                ->where('position_id', $validated['position_id'])
                ->where('id', '!=', $position->id)
                ->first();
                
            if ($existingPosition) {
                return back()->withErrors([
                    'position_id' => 'This position already exists in this section.'
                ])->withInput();
            }
        }

        // Convert empty strings to null for database storage
        if (isset($validated['start_time']) && $validated['start_time'] === '') {
            $validated['start_time'] = null;
        }
        if (isset($validated['end_time']) && $validated['end_time'] === '') {
            $validated['end_time'] = null;
        }

        $position->update($validated);

        \Log::info('Position "' . $position->position_name . '" (' . $position->id . ') updated by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message
        $this->updateDiscordStaffingMessage($position->staffing->event);

        return back()->with('success', 'Position updated successfully.');
    }

    /**
     * Remove the specified position
     */
    public function destroy(StaffingPosition $position)
    {
        $this->authorize('manage-staffings');

        $event = $position->staffing->event;
        
        // Check if position is booked
        if ($position->isBooked()) {
            // Get booking info before clearing
            $bookedUser = $position->bookedBy;
            
            // Delete booking from Control Center if booking ID exists
            if ($position->control_center_booking_id) {
                $this->controlCenterService->deleteBooking($position->control_center_booking_id);
            }
            
            // Clear the booking first (both user_id and discord_user_id)
            $position->update([
                'booked_by_user_id' => null,
                'discord_user_id' => null,
                'vatsim_cid' => null,
                'control_center_booking_id' => null,
            ]);
        }
        
        $position->delete();

        \Log::info('Position "' . $position->position_name . '" (' . $position->id . ') deleted by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message to reflect the deleted position
        $this->updateDiscordStaffingMessage($event);

        return back()->with('success', 'Position removed successfully.');
    }

    /**
     * Reorder positions within sections
     */
    public function reorder(Request $request)
    {
        $this->authorize('manage-staffings');

        $validated = $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|exists:staffing_positions,id',
            'positions.*.order' => 'required|integer',
            'positions.*.staffing_id' => 'required|exists:staffings,id',
        ]);

        foreach ($validated['positions'] as $item) {
            StaffingPosition::where('id', $item['id'])
                ->update([
                    'order' => $item['order'],
                    'staffing_id' => $item['staffing_id'],
                ]);
        }

        // Update Discord message (get event from first position)
        if (!empty($validated['positions'])) {
            $position = StaffingPosition::find($validated['positions'][0]['id']);
            if ($position) {
                $this->updateDiscordStaffingMessage($position->staffing->event);
            }
        }

        return back()->with('success', 'Positions reordered successfully.');
    }

    /**
     * Unbook a position (moderators and admins only)
     */
    public function unbook(Request $request, StaffingPosition $position)
    {
        $user = $request->user();

        // Only moderators and admins can unbook positions
        if (!$user->hasRole('admin') && !$user->hasRole('moderator')) {
            abort(403, 'Only moderators and admins can unbook positions.');
        }

        if (!$position->isBooked()) {
            return back()->withErrors(['position' => 'This position is not booked.']);
        }

        // Get booking info before clearing
        $event = $position->staffing->event;
        $bookedUser = $position->bookedBy;
        
        // Delete booking from Control Center if booking ID exists
        if ($position->control_center_booking_id) {
            $this->controlCenterService->deleteBooking($position->control_center_booking_id);
        }

        $position->update([
            'booked_by_user_id' => null,
            'discord_user_id' => null,
            'vatsim_cid' => null,
            'control_center_booking_id' => null,
        ]);

        \Log::info('Position "' . $position->position_name . '" (' . $position->id . ') unbooked by user: ' . auth()->user()->vatsim_cid);

        // Update Discord message
        $this->updateDiscordStaffingMessage($event);

        return back()->with('success', 'Position unbooked successfully.');
    }

    /**
     * Notify Discord bot about staffing changes
     */
    protected function updateDiscordStaffingMessage($event)
    {
        // Dispatch job to notify bot (runs in background)
        \App\Jobs\UpdateDiscordStaffingMessage::dispatch($event->id, 'updated');
    }
}
