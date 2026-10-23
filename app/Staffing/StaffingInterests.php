<?php

namespace App\Staffing;

use App\Enums\StaffingMode;
use App\Models\EventOccurrence;
use App\Models\StaffingInterest;
use App\Models\User;
use App\Services\DisplayTime;
use App\Services\ZuluTime;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controllers showing interest in staffing an open-interest event.
 */
final class StaffingInterests
{
    /**
     * Create or replace the user's interest in the occurrence.
     *
     * @param  list<int>  $positionIds
     * @param  list<array{from: string, until: string}>  $windows  HH:MM in the user's time zone; none means the whole event
     *
     * @throws AuthorizationException when the user isn't an eligible controller
     * @throws ValidationException when the window is closed or the details are invalid
     */
    public function submit(User $user, EventOccurrence $occurrence, array $positionIds, array $windows, ?string $notes): StaffingInterest
    {
        if ($occurrence->event->isDraft()) {
            throw ValidationException::withMessages(['interest' => 'This event isn’t published yet.']);
        }

        $this->ensureWindowIsOpen($user, $occurrence);

        if ($occurrence->isCancelled()) {
            throw ValidationException::withMessages(['interest' => 'This date has been cancelled.']);
        }

        // For now controllers only staff the next date; the one after opens once it is over.
        if ($occurrence->event->currentOccurrence()?->isNot($occurrence)) {
            throw ValidationException::withMessages(['interest' => 'You can only show interest in the next date of this event.']);
        }

        $validPositions = $occurrence->event->positions()->whereKey($positionIds)->pluck('id');

        if ($validPositions->isEmpty() || $validPositions->count() !== count(array_unique($positionIds))) {
            throw ValidationException::withMessages(['positions' => 'Pick one or more of the positions for this event.']);
        }

        $spans = $this->resolveWindows($occurrence, $windows, DisplayTime::for($user));

        return DB::transaction(function () use ($user, $occurrence, $validPositions, $spans, $notes) {
            $interest = $occurrence->interests()->updateOrCreate(['user_id' => $user->id], ['notes' => $notes]);

            $interest->positions()->sync($validPositions);
            $interest->windows()->delete();
            $interest->windows()->createMany($spans);

            return $interest;
        });
    }

    /**
     * Read each HH:MM window in the user's time zone against the occurrence, which must contain
     * them all without overlaps. No windows means available for the whole event.
     *
     * @param  list<array{from: string, until: string}>  $windows
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     *
     * @throws ValidationException
     */
    private function resolveWindows(EventOccurrence $occurrence, array $windows, DisplayTime $display): array
    {
        if ($windows === []) {
            return [['starts_at' => $occurrence->starts_at, 'ends_at' => $occurrence->ends_at]];
        }

        $spans = [];

        foreach ($windows as $index => $window) {
            $startsAt = ZuluTime::at($occurrence->starts_at, $window['from'], $display->timezone);
            $endsAt = ZuluTime::endAfter($startsAt, $window['until'], $display->timezone);

            if ($endsAt->greaterThan($occurrence->ends_at)) {
                throw ValidationException::withMessages([
                    "windows.{$index}" => 'Your availability must fall within the event ('
                        .$display->range($occurrence->starts_at, $occurrence->ends_at).').',
                ]);
            }

            $spans[$index] = ['starts_at' => $startsAt, 'ends_at' => $endsAt];
        }

        uasort($spans, fn (array $a, array $b) => $a['starts_at'] <=> $b['starts_at']);

        $previous = null;

        foreach ($spans as $index => $span) {
            if ($previous && $span['starts_at']->lessThan($previous['ends_at'])) {
                throw ValidationException::withMessages(["windows.{$index}" => 'This overlaps another of your windows.']);
            }

            $previous = $span;
        }

        return array_values($spans);
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function withdraw(User $user, EventOccurrence $occurrence): void
    {
        $this->ensureWindowIsOpen($user, $occurrence);

        $occurrence->interests()->where('user_id', $user->id)->delete();
    }

    private function ensureWindowIsOpen(User $user, EventOccurrence $occurrence): void
    {
        if (! $user->isEligibleController($occurrence->event)) {
            throw new AuthorizationException('Only controllers can show interest in staffing.');
        }

        if ($occurrence->event->staffing_mode !== StaffingMode::OpenInterest) {
            throw ValidationException::withMessages(['interest' => 'This event does not take staffing interest.']);
        }

        $message = match ($occurrence->interestWindowState()) {
            'upcoming' => 'Interest opens '.DisplayTime::for($user)->moment($occurrence->interestOpensAt()).'.',
            'closed' => 'The window for showing interest has closed.',
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['interest' => $message]);
        }
    }
}
