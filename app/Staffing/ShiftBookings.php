<?php

namespace App\Staffing;

use App\Enums\BookingRefusal;
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
     * Why someone can't book shifts on the date right now, or null when they can. Reasons
     * about the date come first, so the page explains those before asking anyone to sign in.
     */
    public function refusal(?User $user, EventOccurrence $occurrence): ?BookingRefusal
    {
        $event = $occurrence->event;

        return match (true) {
            $event->isDraft() => BookingRefusal::Draft,
            $event->staffing_mode !== StaffingMode::Shifts => BookingRefusal::NotShifts,
            $occurrence->hasStarted() => BookingRefusal::Started,
            $occurrence->isCancelled() => BookingRefusal::Cancelled,
            // For now controllers only book the next date; the one after opens once it is over.
            $event->currentOccurrence()?->isNot($occurrence) => BookingRefusal::NotNextDate,
            $user === null => BookingRefusal::SignInNeeded,
            ! $user->isEligibleController($event) => BookingRefusal::NotController,
            default => null,
        };
    }

    /**
     * @throws AuthorizationException when the user isn't an eligible controller
     * @throws ValidationException when the shift can't be booked
     */
    public function book(User $user, PositionShift $shift, EventOccurrence $occurrence): ShiftBooking
    {
        if ($shift->position->event_id !== $occurrence->event_id) {
            throw ValidationException::withMessages(['booking' => 'That shift belongs to a different event.']);
        }

        if ($refusal = $this->refusal($user, $occurrence)) {
            $this->refuse($refusal);
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
                $booking = ShiftBooking::create([
                    'position_shift_id' => $shift->id,
                    'event_occurrence_id' => $occurrence->id,
                    'user_id' => $user->id,
                ]);
                StaffingChanged::forOccurrence($occurrence);

                return $booking;
            } catch (UniqueConstraintViolationException) {
                // Someone booked it between the check above and this insert.
                $this->alreadyBooked();
            }
        });
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    private function refuse(BookingRefusal $refusal): never
    {
        if ($refusal === BookingRefusal::SignInNeeded || $refusal === BookingRefusal::NotController) {
            throw new AuthorizationException('Only controllers can book shifts.');
        }

        throw ValidationException::withMessages(['booking' => match ($refusal) {
            BookingRefusal::Draft => 'This event isn’t published yet.',
            BookingRefusal::NotShifts => 'This event does not use shift booking.',
            BookingRefusal::Started => 'Booking closed when the event started.',
            BookingRefusal::Cancelled => 'This date has been cancelled.',
            BookingRefusal::NotNextDate => 'Shifts can only be booked for the next date of this event.',
        }]);
    }

    private function alreadyBooked(): never
    {
        throw ValidationException::withMessages(['booking' => 'Someone else has already booked this shift.']);
    }

    /**
     * Controllers can drop their own booking until the event starts; staffing managers can
     * remove any booking until the event ends.
     */
    public function canCancel(User $actor, ShiftBooking $booking): bool
    {
        $occurrence = $booking->occurrence;

        return $actor->can('manageStaffing', $occurrence->event)
            ? ! $occurrence->hasEnded()
            : $booking->user_id === $actor->id && ! $occurrence->hasStarted();
    }

    /**
     * @throws AuthorizationException
     */
    public function cancel(User $actor, ShiftBooking $booking): void
    {
        $occurrence = $booking->occurrence;

        if (! $this->canCancel($actor, $booking)) {
            throw new AuthorizationException($actor->can('manageStaffing', $occurrence->event)
                ? 'Bookings are archived once the event has ended.'
                : 'You can no longer cancel this booking.');
        }

        $booking->delete();
        StaffingChanged::forOccurrence($occurrence);
    }
}
