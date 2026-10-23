<?php

namespace App\Staffing;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Models\StaffingInterest;
use App\Models\StaffingInterestWindow;
use App\Models\User;
use App\Services\DisplayTime;
use Carbon\CarbonImmutable;

/**
 * Shapes staffing data for the pages, applying who may see what:
 *
 * - Shift bookings are public.
 * - Interest is visible only to the controller who showed it, to the event's staff and to
 *   vACC Staff.
 * - A roster is visible to everyone once published, and before that to the event's staff
 *   and to vACC Staff (see EventPolicy).
 */
final class StaffingPresenter
{
    public function __construct(private DisplayTime $display) {}

    /**
     * Staffing as shown on the public event page.
     *
     * @return array<string, mixed>
     */
    public function forEventPage(Event $event, EventOccurrence $occurrence, ?User $viewer): array
    {
        // vACC Staff may see what staffing managers see, without changing anything.
        $canSeeAsStaff = (bool) $viewer?->can('viewStaffing', $event);
        $positions = $event->positions()->with('shifts')->get();

        $staffing = [
            'mode' => $event->staffing_mode->value,
            'canManage' => (bool) $viewer?->can('manageStaffing', $event),
            'viewer' => [
                'signedIn' => $viewer !== null,
                'eligible' => (bool) $viewer?->isEligibleController($event),
            ],
            'occurrence' => $this->occurrence($occurrence),
            'occurrences' => $event->occurrences->map(fn (EventOccurrence $item) => $this->occurrence($item))->all(),
        ];

        return match ($event->staffing_mode) {
            StaffingMode::Shifts => [
                ...$staffing,
                'bookingOpen' => ! $occurrence->hasStarted() && ! $occurrence->isCancelled(),
                'positions' => array_values(array_filter($this->positionsWithBookings($positions, $occurrence, $viewer), fn (array $position) => $position['shifts'] !== [])),
            ],
            StaffingMode::OpenInterest => [
                ...$staffing,
                'positions' => $positions->map(fn (EventPosition $position) => $this->position($position))->all(),
                'interest' => [
                    'state' => $occurrence->interestWindowState(),
                    'opensAt' => $this->moment($occurrence->interestOpensAt()),
                    'closesAt' => $this->moment($occurrence->interestClosesAt()),
                    'mine' => $viewer ? $this->interest($occurrence->interests()->where('user_id', $viewer->id)->first()) : null,
                    'all' => $canSeeAsStaff ? $this->interests($occurrence) : null,
                ],
                'rosterPublished' => $occurrence->isRosterPublished(),
                'roster' => $occurrence->isRosterPublished() || $canSeeAsStaff ? $this->roster($occurrence) : null,
            ],
            default => $staffing,
        };
    }

    /**
     * Everything Event Staff need to set up and run staffing for an occurrence.
     *
     * @return array<string, mixed>
     */
    public function forStaff(Event $event, EventOccurrence $occurrence, User $viewer): array
    {
        $positions = $event->positions()->with('shifts')->get();

        return [
            'settings' => [
                'mode' => $event->staffing_mode->value,
                'interestOpensHours' => $event->interest_opens_hours,
                'interestClosesHours' => $event->interest_closes_hours,
            ],
            'occurrence' => [
                ...$this->occurrence($occurrence),
                'interestState' => $occurrence->interestWindowState(),
                'interestOpensAt' => $this->moment($occurrence->interestOpensAt()),
                'rosterPublished' => $occurrence->isRosterPublished(),
                'rosterArchived' => $occurrence->isRosterArchived(),
                'startsAt' => $occurrence->starts_at->utc()->toIso8601String(),
                'durationMinutes' => (int) $occurrence->starts_at->diffInMinutes($occurrence->ends_at),
            ],
            'positions' => $this->positionsWithBookings($positions, $occurrence, $viewer),
            'interests' => $this->interests($occurrence),
            'roster' => $this->roster($occurrence),
            'controllers' => User::query()->controllers($event)->orderBy('name')->get()
                ->map(fn (User $user) => $this->controller($user))->all(),
        ];
    }

    /**
     * @param  iterable<EventPosition>  $positions
     * @return list<array<string, mixed>>
     */
    private function positionsWithBookings(iterable $positions, EventOccurrence $occurrence, ?User $viewer): array
    {
        $bookings = $occurrence->bookings()->with('user')->get()->keyBy('position_shift_id');
        $result = [];

        foreach ($positions as $position) {
            $result[] = [
                ...$this->position($position),
                'shifts' => $position->shifts->map(fn (PositionShift $shift) => [
                    'id' => $shift->id,
                    'time' => $this->display->range($shift->startsAtFor($occurrence), $shift->endsAtFor($occurrence)),
                    'startOffset' => $shift->starts_offset,
                    'endOffset' => $shift->ends_offset,
                    'booking' => $this->booking($bookings->get($shift->id), $occurrence, $viewer),
                ])->all(),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function booking(?ShiftBooking $booking, EventOccurrence $occurrence, ?User $viewer): ?array
    {
        if (! $booking) {
            return null;
        }

        $isMine = $viewer !== null && $booking->user_id === $viewer->id;

        return [
            'id' => $booking->id,
            'controller' => $this->controller($booking->user),
            'isMine' => $isMine,
            'canCancel' => ($isMine && ! $occurrence->hasStarted()) || ($viewer?->can('manageStaffing', $occurrence->event) && ! $occurrence->hasEnded()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function position(EventPosition $position): array
    {
        return [
            'id' => $position->id,
            'callsign' => $position->callsign,
            'frequency' => $position->frequency,
        ];
    }

    /**
     * Everyone's interest in the occurrence, by when they're first available.
     *
     * @return list<array<string, mixed>>
     */
    private function interests(EventOccurrence $occurrence): array
    {
        return $occurrence->interests()->with(['user', 'positions', 'windows'])->get()
            ->sortBy(fn (StaffingInterest $interest) => $interest->windows->first()?->starts_at)
            ->values()
            ->map(fn (StaffingInterest $interest) => [
                ...$this->interest($interest),
                'controller' => $this->controller($interest->user),
            ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function interest(?StaffingInterest $interest): ?array
    {
        if (! $interest) {
            return null;
        }

        return [
            'id' => $interest->id,
            'positionIds' => $interest->positions->pluck('id')->all(),
            'callsigns' => $interest->positions->pluck('callsign')->all(),
            'windows' => $interest->windows->map(fn (StaffingInterestWindow $window) => [
                'from' => $this->display->time($window->starts_at),
                'until' => $this->display->time($window->ends_at),
                'time' => $this->display->range($window->starts_at, $window->ends_at),
                'fromOffset' => (int) $interest->occurrence->starts_at->diffInMinutes($window->starts_at),
                'untilOffset' => (int) $interest->occurrence->starts_at->diffInMinutes($window->ends_at),
            ])->all(),
            'notes' => $interest->notes,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roster(EventOccurrence $occurrence): array
    {
        return $occurrence->rosterEntries()->with(['position', 'user'])->get()
            ->map(fn (RosterEntry $entry) => [
                'id' => $entry->id,
                'positionId' => $entry->event_position_id,
                'callsign' => $entry->position->callsign,
                'controller' => $this->controller($entry->user),
                'time' => $this->display->range($entry->starts_at, $entry->ends_at),
                'startOffset' => (int) $occurrence->starts_at->diffInMinutes($entry->starts_at),
                'endOffset' => (int) $occurrence->starts_at->diffInMinutes($entry->ends_at),
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function controller(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'cid' => $user->vatsim_cid,
            'rating' => $user->rating?->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function occurrence(EventOccurrence $occurrence): array
    {
        return [
            'id' => $occurrence->id,
            'date' => $this->display->date($occurrence->starts_at, 'D j M Y'),
            'time' => $this->display->range($occurrence->starts_at, $occurrence->ends_at),
            'hasStarted' => $occurrence->hasStarted(),
            'hasEnded' => $occurrence->hasEnded(),
            'cancelled' => $occurrence->isCancelled(),
        ];
    }

    private function moment(CarbonImmutable $moment): string
    {
        return $this->display->moment($moment);
    }
}
