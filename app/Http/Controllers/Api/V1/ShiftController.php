<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StaffingMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\PositionShift;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends Controller
{
    /**
     * List every shift on a date, booked or open.
     *
     * Shifts are ordered by position, then by start. An open shift has no `controller`.
     * Events that aren't staffed through shifts have none.
     */
    public function index(EventOccurrence $occurrence): AnonymousResourceCollection
    {
        $event = $occurrence->event;

        // A Draft's dates are not found, rather than forbidden, so the Draft isn't revealed.
        abort_if($event->isDraft(), 404);

        if ($event->staffing_mode !== StaffingMode::Shifts) {
            return ShiftResource::collection([]);
        }

        $bookings = $occurrence->bookings()->with('user')->get()->keyBy('position_shift_id');

        $shifts = $event->positions()->with('shifts')->get()
            ->flatMap(fn (EventPosition $position) => $position->shifts->map(fn (PositionShift $shift) => new ShiftResource(
                $shift->setRelation('position', $position),
                $occurrence,
                $bookings->get($shift->id),
            )));

        return ShiftResource::collection($shifts);
    }
}
