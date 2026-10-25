<?php

namespace App\Integrations\ControlCenter;

use App\Enums\StaffingMode;
use App\Integrations\Feature;
use App\Integrations\Integrations;
use App\Integrations\Syncable;
use App\Integrations\SyncContext;
use App\Integrations\SyncResult;
use App\Models\EventOccurrence;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Staffing\StaffingChanged;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event as Events;
use Throwable;

/**
 * Sends the Hub's staffing to Control Center as event bookings, which Control Center passes on
 * to VATSIM: Shift Bookings as they're made, and Roster Entries once their roster is published.
 * Whenever staffing changes on a date, that date's bookings are brought in line (PushBookings);
 * an hourly sweep catches anything missed, and switching it on sends every upcoming booking.
 *
 * Switched off, it sends nothing more and leaves what it sent alone.
 */
class BookingPush extends Feature implements Syncable
{
    /** Tagged as an event booking in Control Center. */
    public const int EVENT_TAG = 3;

    public const string SOURCE = 'EVENTHUB';

    public function key(): string
    {
        return 'bookings';
    }

    public function name(): string
    {
        return 'Booking push';
    }

    public function description(): string
    {
        return 'Sends shift bookings, and rosters once published, to Control Center as event bookings, which it passes on to VATSIM. Needs an API key that can edit.';
    }

    /**
     * Two pushes to the network would book every position twice.
     */
    public function conflictsWith(): array
    {
        return ['vatsim-bookings.bookings'];
    }

    public function schedule(Event $event): void
    {
        $event->hourly();
    }

    public function boot(): void
    {
        Events::listen(StaffingChanged::class, function (StaffingChanged $changed) {
            if (! $this->isOn()) {
                return;
            }

            foreach ($changed->occurrenceIds as $occurrenceId) {
                // Control Center being down must never stop the booking or change that caused this,
                // even where the queue runs jobs straight away. The queue or the sweep tries again.
                try {
                    PushBookings::dispatch($occurrenceId);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }

    /**
     * Queue every date that has, or had, bookings to send. Nothing is sent from here.
     */
    public function sync(SyncContext $context): SyncResult
    {
        $occurrenceIds = EventOccurrence::query()
            ->where('starts_at', '>', now())
            ->whereHas('event', fn ($query) => $query->whereNot('staffing_mode', StaffingMode::None))
            ->pluck('id')
            ->merge(PushedBooking::query()->where('starts_at', '>', now())->distinct()->pluck('event_occurrence_id'))
            ->unique();

        foreach ($occurrenceIds as $occurrenceId) {
            PushBookings::dispatch($occurrenceId)->afterCommit();
        }

        return SyncResult::succeeded();
    }

    public function needsAttention(): array
    {
        return PushedBooking::query()
            ->where('status', PushedBooking::NOT_SENT)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (PushedBooking $booking) => "{$booking->callsign}, {$booking->starts_at->format('D j M H:i')}–{$booking->ends_at->format('H:i')}z"
                .($booking->cid ? " (CID {$booking->cid})" : '').": not sent. {$booking->reason}")
            ->all();
    }

    /**
     * The bookings Control Center should have for the date, by "source:id". Only what's still
     * to come: Control Center refuses bookings in the past.
     *
     * @return Collection<string, array{source: string, source_id: int, cid: ?int, callsign: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public static function wanted(?EventOccurrence $occurrence): Collection
    {
        $event = $occurrence?->event;

        if ($occurrence === null || $event->isDraft() || $occurrence->isCancelled()) {
            return collect();
        }

        $bookings = match ($event->staffing_mode) {
            StaffingMode::Shifts => $occurrence->bookings()->with('shift.position', 'user')->get()->map(fn (ShiftBooking $booking) => [
                'source' => 'shift_booking',
                'source_id' => $booking->id,
                'cid' => $booking->user->vatsim_cid,
                'callsign' => $booking->shift->position->callsign,
                'starts_at' => $booking->shift->startsAtFor($occurrence),
                'ends_at' => $booking->shift->endsAtFor($occurrence),
            ]),
            StaffingMode::OpenInterest => $occurrence->isRosterPublished()
                ? $occurrence->rosterEntries()->with('position', 'user')->get()->map(fn (RosterEntry $entry) => [
                    'source' => 'roster_entry',
                    'source_id' => $entry->id,
                    'cid' => $entry->user->vatsim_cid,
                    'callsign' => $entry->position->callsign,
                    'starts_at' => $entry->starts_at,
                    'ends_at' => $entry->ends_at,
                ])
                : collect(),
            default => collect(),
        };

        return $bookings
            ->filter(fn (array $booking) => $booking['starts_at']->isFuture())
            ->keyBy(fn (array $booking) => "{$booking['source']}:{$booking['source_id']}");
    }

    /**
     * The booking as Control Center takes it: a date and times in UTC.
     *
     * @param  array{cid: ?int, callsign: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $booking
     * @return array<string, mixed>
     */
    public static function payload(array $booking): array
    {
        return [
            'cid' => $booking['cid'],
            'date' => $booking['starts_at']->utc()->format('d/m/Y'),
            'start_at' => $booking['starts_at']->utc()->format('H:i'),
            'end_at' => $booking['ends_at']->utc()->format('H:i'),
            'position' => $booking['callsign'],
            'tag' => self::EVENT_TAG,
            'source' => self::SOURCE,
        ];
    }

    private function isOn(): bool
    {
        $integrations = app(Integrations::class);

        return $integrations->isOn(app(ControlCenter::class), $this);
    }
}
