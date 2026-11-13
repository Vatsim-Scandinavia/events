<?php

namespace App\Staffing;

use App\Enums\RosterState;
use App\Enums\StaffingMode;
use App\Integrations\Integrations;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\EventSection;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Models\StaffingInterest;
use App\Models\StaffingInterestWindow;
use App\Models\User;
use App\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Shapes staffing data for the pages, applying who may see what:
 *
 * - The Event page's shifts and roster are the date's Roster, as the viewer may see it (see
 *   Roster).
 * - Interest is visible only to the controller who showed it, to the event's staff and to
 *   vACC Staff.
 * - Notes from Integrations (see StaffingNotes) are for the event's staff, vACC Staff and the
 *   controller they're about.
 *
 * Positions and roster entries come in the order staff set, each with the Section it falls
 * under, so the pages can show a heading wherever the Section changes.
 */
final class StaffingPresenter
{
    public function __construct(
        private DisplayTime $display,
        private Integrations $integrations,
        private ShiftBookings $shiftBookings,
        private StaffingInterests $staffingInterests,
    ) {}

    /**
     * Staffing as shown on the public event page.
     *
     * @return array<string, mixed>
     */
    public function forEventPage(Event $event, EventOccurrence $occurrence, ?User $viewer): array
    {
        // vACC Staff may see what staffing managers see, without changing anything.
        $canSeeAsStaff = (bool) $viewer?->can('viewStaffing', $event);
        $roster = Roster::of($occurrence, $viewer);
        $notes = $viewer !== null ? $this->notes($occurrence) : [];
        // A controller hears what they should know before working each position.
        $noteFor = $viewer?->isEligibleController($event) ? $viewer : null;

        $staffing = [
            'mode' => $event->staffing_mode->value,
            // Staff see a Draft's staffing, but controllers can't staff it until it's published.
            'draft' => $event->isDraft(),
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
                // Why the viewer can't book here, if they can't, so the page explains it.
                'booking' => ['refusal' => $this->shiftBookings->refusal($viewer, $occurrence)?->value],
                'positions' => $roster->items()
                    ->groupBy(fn (RosterItem $item) => $item->position->id)
                    ->map(fn (Collection $items) => [
                        ...$this->position($items->first()->position, $items->first()->section, $noteFor),
                        'shifts' => $items->map(fn (RosterItem $item) => $this->shift($item->shift(), $occurrence, $item->booking, $viewer, $notes, $canSeeAsStaff))->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            StaffingMode::OpenInterest => [
                ...$staffing,
                'positions' => $this->positions($event, $noteFor),
                'interest' => [
                    'state' => $occurrence->interestWindowState(),
                    // Why the viewer can't show interest here, if they can't, so the page explains it.
                    'refusal' => $this->staffingInterests->refusal($viewer, $occurrence)?->value,
                    'opensAt' => $this->moment($occurrence->interestOpensAt()),
                    'closesAt' => $this->moment($occurrence->interestClosesAt()),
                    'mine' => $viewer ? $this->interest($occurrence->interests()->where('user_id', $viewer->id)->first()) : null,
                    'all' => $canSeeAsStaff ? $this->interests($occurrence) : null,
                ],
                'rosterPublished' => $occurrence->isRosterPublished(),
                'roster' => $roster->state() === RosterState::Shown
                    ? $roster->items()->map(fn (RosterItem $item) => $this->rosterEntry($item->rosterEntry(), $item->position, $item->section, $occurrence, $viewer, $notes, $canSeeAsStaff))->all()
                    : null,
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
        $sections = $event->sectionsFor($positions);
        $notes = $this->notes($occurrence);

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
                // A Draft's roster can be laid but not published until the event is.
                'eventDraft' => $event->isDraft(),
                'startsAt' => $occurrence->starts_at->utc()->toIso8601String(),
                'durationMinutes' => (int) $occurrence->starts_at->diffInMinutes($occurrence->ends_at),
            ],
            'positions' => $this->positionsWithBookings($positions, $sections, $occurrence, $viewer, $notes, true),
            // Every Section, even one with no positions under it, to lay out the planner.
            'sections' => $event->sections->map(fn (EventSection $section) => [
                'id' => $section->id,
                'label' => $section->label,
                'sort' => $section->sort,
            ])->all(),
            'interests' => $this->interests($occurrence),
            'roster' => $this->roster($occurrence, $positions, $sections, $viewer, $notes, true),
            'controllers' => User::query()->controllers($event)->get()->sortBy(fn (User $user) => mb_strtolower($user->displayName()))->values()
                ->map(fn (User $user) => $this->controller($user))->all(),
        ];
    }

    /**
     * @param  iterable<EventPosition>  $positions
     * @param  array<int, EventSection|null>  $sections  by position id
     * @param  array<string, list<string>>  $notes
     * @return list<array<string, mixed>>
     */
    private function positionsWithBookings(iterable $positions, array $sections, EventOccurrence $occurrence, ?User $viewer, array $notes, bool $asStaff, ?User $noteFor = null): array
    {
        $bookings = $occurrence->bookings()->chaperone('occurrence')->with('user')->get()->keyBy('position_shift_id');
        $result = [];

        foreach ($positions as $position) {
            $result[] = [
                ...$this->position($position, $sections[$position->id] ?? null, $noteFor),
                'shifts' => $position->shifts->map(fn (PositionShift $shift) => $this->shift($shift, $occurrence, $bookings->get($shift->id), $viewer, $notes, $asStaff))->all(),
            ];
        }

        return $result;
    }

    /**
     * A shift as it falls on the date, with its booking there.
     *
     * @param  array<string, list<string>>  $notes
     * @return array<string, mixed>
     */
    private function shift(PositionShift $shift, EventOccurrence $occurrence, ?ShiftBooking $booking, ?User $viewer, array $notes, bool $asStaff): array
    {
        return [
            'id' => $shift->id,
            'time' => $this->display->range($shift->startsAtFor($occurrence), $shift->endsAtFor($occurrence)),
            'startOffset' => $shift->starts_offset,
            'endOffset' => $shift->ends_offset,
            'booking' => $this->booking($booking, $occurrence, $viewer, $notes, $asStaff),
        ];
    }

    /**
     * @param  array<string, list<string>>  $notes
     * @return array<string, mixed>|null
     */
    private function booking(?ShiftBooking $booking, EventOccurrence $occurrence, ?User $viewer, array $notes, bool $asStaff): ?array
    {
        if (! $booking) {
            return null;
        }

        $isMine = $viewer !== null && $booking->user_id === $viewer->id;

        return [
            'id' => $booking->id,
            'controller' => $this->controller($booking->user),
            'isMine' => $isMine,
            'canCancel' => $viewer !== null && $this->shiftBookings->canCancel($viewer, $booking),
            'notes' => $asStaff || $isMine ? $notes["shift_booking:{$booking->id}"] ?? [] : [],
        ];
    }

    /**
     * Every position, in order, for the controller picking which they'd like to work.
     *
     * @param  ?User  $noteFor  The controller to tell what they should know before working each.
     * @return list<array<string, mixed>>
     */
    private function positions(Event $event, ?User $noteFor): array
    {
        $positions = $event->positions()->get();
        $sections = $event->sectionsFor($positions);

        return $positions->map(fn (EventPosition $position) => $this->position($position, $sections[$position->id], $noteFor))->all();
    }

    /**
     * @param  ?EventSection  $section  The Section the position falls under.
     * @param  ?User  $noteFor  The controller to tell what they should know before working it.
     * @return array<string, mixed>
     */
    private function position(EventPosition $position, ?EventSection $section, ?User $noteFor = null): array
    {
        return [
            'id' => $position->id,
            'callsign' => $position->callsign,
            'frequency' => $position->frequency,
            'sort' => $position->sort,
            'section' => $this->section($section),
            'note' => $noteFor ? $this->positionNote($noteFor, $position) : null,
        ];
    }

    /**
     * @return array{id: int, label: string|null}|null
     */
    private function section(?EventSection $section): ?array
    {
        return $section ? ['id' => $section->id, 'label' => $section->label] : null;
    }

    /**
     * What Integrations have to say about the date's bookings and roster entries.
     *
     * @return array<string, list<string>>
     */
    private function notes(EventOccurrence $occurrence): array
    {
        return array_merge_recursive(...array_map(fn (StaffingNotes $source) => $source->staffingNotes($occurrence), $this->integrations->staffingNotes()) ?: [[]]);
    }

    private function positionNote(User $controller, EventPosition $position): ?string
    {
        foreach ($this->integrations->staffingNotes() as $source) {
            if ($note = $source->positionNote($controller, $position)) {
                return $note;
            }
        }

        return null;
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
     * The roster in the order of its positions, then by time.
     *
     * @param  Collection<int, EventPosition>  $positions  in order
     * @param  array<int, EventSection|null>  $sections  by position id
     * @param  array<string, list<string>>  $notes
     * @return list<array<string, mixed>>
     */
    private function roster(EventOccurrence $occurrence, Collection $positions, array $sections, ?User $viewer, array $notes, bool $asStaff): array
    {
        $order = $positions->pluck('id')->flip();

        return $occurrence->rosterEntries()->with(['position', 'user'])->get()
            ->sortBy([
                fn (RosterEntry $a, RosterEntry $b) => $order[$a->event_position_id] <=> $order[$b->event_position_id],
                fn (RosterEntry $a, RosterEntry $b) => $a->starts_at <=> $b->starts_at,
            ])
            ->values()
            ->map(fn (RosterEntry $entry) => $this->rosterEntry($entry, $entry->position, $sections[$entry->event_position_id] ?? null, $occurrence, $viewer, $notes, $asStaff))
            ->all();
    }

    /**
     * @param  ?EventSection  $section  The Section the entry's position falls under.
     * @param  array<string, list<string>>  $notes
     * @return array<string, mixed>
     */
    private function rosterEntry(RosterEntry $entry, EventPosition $position, ?EventSection $section, EventOccurrence $occurrence, ?User $viewer, array $notes, bool $asStaff): array
    {
        return [
            'id' => $entry->id,
            'positionId' => $position->id,
            'callsign' => $position->callsign,
            'section' => $this->section($section),
            'controller' => $this->controller($entry->user),
            'time' => $this->display->range($entry->starts_at, $entry->ends_at),
            'startOffset' => (int) $occurrence->starts_at->diffInMinutes($entry->starts_at),
            'endOffset' => (int) $occurrence->starts_at->diffInMinutes($entry->ends_at),
            'notes' => $asStaff || $entry->user_id === $viewer?->id ? $notes["roster_entry:{$entry->id}"] ?? [] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function controller(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->displayName(),
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
