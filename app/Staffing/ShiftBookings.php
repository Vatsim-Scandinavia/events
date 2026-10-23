<?php

namespace App\Staffing;

use App\Enums\StaffingMode;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\ShiftBooking;
use App\Models\User;
use App\Services\DisplayTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controllers booking pre-defined shifts, first come, first served.
 */
final class ShiftBookings
{
    /**
     * @throws AuthorizationException when the user isn't an eligible controller
     * @throws ValidationException when the shift can't be booked
     */
    public function book(User $user, PositionShift $shift, EventOccurrence $occurrence): ShiftBooking
    {
        $event = $occurrence->event;

        if ($shift->position->event_id !== $event->id) {
            throw ValidationException::withMessages(['booking' => 'That shift belongs to a different event.']);
        }

        if ($event->isDraft()) {
            throw ValidationException::withMessages(['booking' => 'This event isn’t published yet.']);
        }

        if (! $user->isEligibleController($event)) {
            throw new AuthorizationException('Only controllers can book shifts.');
        }

        if ($event->staffing_mode !== StaffingMode::Shifts) {
            throw ValidationException::withMessages(['booking' => 'This event does not use shift booking.']);
        }

        if ($occurrence->hasStarted()) {
            throw ValidationException::withMessages(['booking' => 'Booking closed when the event started.']);
        }

        if ($occurrence->isCancelled()) {
            throw ValidationException::withMessages(['booking' => 'This date has been cancelled.']);
        }

        // For now controllers only book the next date; the one after opens once it is over.
        if ($event->currentOccurrence()?->isNot($occurrence)) {
            throw ValidationException::withMessages(['booking' => 'Shifts can only be booked for the next date of this event.']);
        }

        $startsAt = $shift->startsAtFor($occurrence);
        $endsAt = $shift->endsAtFor($occurrence);

        return DB::transaction(function () use ($user, $shift, $occurrence, $startsAt, $endsAt) {
            // Serialise a controller's bookings so two simultaneous requests can't both pass the overlap check.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $taken = ShiftBooking::query()
                ->where('position_shift_id', $shift->id)
                ->where('event_occurrence_id', $occurrence->id)
                ->exists();

            if ($taken) {
                $this->alreadyBooked();
            }

            $clash = $user->shiftBookings()
                ->with(['shift.position', 'occurrence'])
                ->whereHas('occurrence', fn ($query) => $query->where('ends_at', '>', now()))
                ->get()
                ->first(fn (ShiftBooking $booking) => $booking->shift->startsAtFor($booking->occurrence)->lessThan($endsAt)
                    && $booking->shift->endsAtFor($booking->occurrence)->greaterThan($startsAt));

            if ($clash) {
                $callsign = $clash->shift->position->callsign;

                throw ValidationException::withMessages([
                    'booking' => "This shift overlaps your booking on {$callsign} ("
                        .DisplayTime::for($user)->range($clash->shift->startsAtFor($clash->occurrence), $clash->shift->endsAtFor($clash->occurrence)).').',
                ]);
            }

            try {
                return ShiftBooking::create([
                    'position_shift_id' => $shift->id,
                    'event_occurrence_id' => $occurrence->id,
                    'user_id' => $user->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Someone booked it between the check above and this insert.
                $this->alreadyBooked();
            }
        });
    }

    private function alreadyBooked(): never
    {
        throw ValidationException::withMessages(['booking' => 'Someone else has already booked this shift.']);
    }

    /**
     * Controllers can drop their own booking until the event starts; staffing managers can
     * remove any booking until the event ends.
     *
     * @throws AuthorizationException
     */
    public function cancel(User $actor, ShiftBooking $booking): void
    {
        $occurrence = $booking->occurrence;

        if ($actor->can('manageStaffing', $occurrence->event)) {
            if ($occurrence->hasEnded()) {
                throw new AuthorizationException('Bookings are archived once the event has ended.');
            }
        } elseif ($booking->user_id !== $actor->id || $occurrence->hasStarted()) {
            throw new AuthorizationException('You can no longer cancel this booking.');
        }

        $booking->delete();
    }
}
