<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventInstance;

/**
 * Event Instance Controller
 *
 * Manages individual occurrences of events. Allows soft deletion (removal)
 * of specific dates from recurring series and restoration of removed dates.
 */
class EventInstanceController extends Controller
{
    /**
     * Soft delete a specific occurrence of an event.
     *
     * Removes this specific date from the schedule while keeping other
     * occurrences in the series. The instance can be restored later.
     * Used when a specific date needs to be cancelled (e.g., holiday).
     *
     * @param EventInstance $instance
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(EventInstance $instance)
    {
        $this->authorize('destroy', $instance);

        $instance->delete();

        return back()->with('success', 'The specific occurrence has been removed.');
    }

    /**
     * Restore a previously removed occurrence.
     *
     * Un-deletes a soft-deleted instance, adding it back to the schedule.
     * Useful when a cancelled date needs to be reinstated.
     *
     * @param int $id The soft-deleted instance ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {
        $instance = EventInstance::withTrashed()->findOrFail($id);
        $this->authorize('restore', $instance);

        $instance->restore();

        return back()->with('success', 'Occurrence restored to the schedule.');
    }
}
