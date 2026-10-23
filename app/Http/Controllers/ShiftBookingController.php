<?php

namespace App\Http\Controllers;

use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\ShiftBooking;
use App\Staffing\ShiftBookings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftBookingController extends Controller
{
    public function store(Request $request, EventOccurrence $occurrence, PositionShift $shift, ShiftBookings $bookings): RedirectResponse
    {
        $bookings->book($request->user(), $shift, $occurrence);

        return Inertia::flash('success', "You're booked on {$shift->position->callsign}.")->back();
    }

    public function destroy(Request $request, ShiftBooking $booking, ShiftBookings $bookings): RedirectResponse
    {
        $bookings->cancel($request->user(), $booking);

        return Inertia::flash('success', 'The booking was cancelled.')->back();
    }
}
