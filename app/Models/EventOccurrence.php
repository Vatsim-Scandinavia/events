<?php

namespace App\Models;

use App\Enums\StaffingMode;
use App\Staffing\StaffingChanged;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * One date an event takes place on. Bookings, interest and rosters belong here,
 * while positions and shifts belong to the event.
 */
class EventOccurrence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['starts_at', 'ends_at', 'cancelled_at', 'roster_published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'roster_published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<ShiftBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ShiftBooking::class);
    }

    /**
     * @return HasMany<StaffingInterest, $this>
     */
    public function interests(): HasMany
    {
        // Interests read the occurrence for their time offsets, so hand it to them.
        return $this->hasMany(StaffingInterest::class)->chaperone('occurrence');
    }

    /**
     * @return HasMany<RosterEntry, $this>
     */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class)->orderBy('starts_at');
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->lessThanOrEqualTo(now());
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->lessThanOrEqualTo(now());
    }

    /**
     * Cancelled on its own, or because the whole event was.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null || $this->event->isCancelled();
    }

    /**
     * Calls off this date. Nothing is deleted: bookings, interest and its roster stay so a
     * restored date picks up where it was.
     *
     * @throws ValidationException when the date has started
     */
    public function cancel(): void
    {
        if ($this->hasStarted()) {
            throw ValidationException::withMessages(['cancel' => 'This date has already started, so it can’t be cancelled.']);
        }

        $this->update(['cancelled_at' => $this->cancelled_at ?? now()]);
        StaffingChanged::forOccurrence($this);
    }

    /**
     * @throws ValidationException when the date has started or the whole event is cancelled
     */
    public function restore(): void
    {
        if ($this->hasStarted()) {
            throw ValidationException::withMessages(['cancel' => 'This date has already started, so it can’t be restored.']);
        }

        if ($this->event->isCancelled()) {
            throw ValidationException::withMessages(['cancel' => 'The whole event is cancelled. Restore the event first.']);
        }

        $this->update(['cancelled_at' => null]);
        StaffingChanged::forOccurrence($this);
    }

    public function interestOpensAt(): CarbonImmutable
    {
        return $this->starts_at->subHours($this->event->interest_opens_hours);
    }

    public function interestClosesAt(): CarbonImmutable
    {
        return $this->starts_at->subHours($this->event->interest_closes_hours);
    }

    /**
     * Whether controllers can currently show interest: 'upcoming', 'open' or 'closed'.
     */
    public function interestWindowState(): string
    {
        return match (true) {
            now()->lessThan($this->interestOpensAt()) => 'upcoming',
            now()->lessThan($this->interestClosesAt()) => 'open',
            default => 'closed',
        };
    }

    public function isRosterPublished(): bool
    {
        return $this->roster_published_at !== null;
    }

    /**
     * Once the occurrence is over its roster is archived and can no longer change.
     */
    public function isRosterArchived(): bool
    {
        return $this->hasEnded();
    }

    /**
     * Shows the roster to everyone. Its entries are saved with the rest of the staffing plan
     * (see StaffingPlan).
     *
     * @throws ValidationException
     */
    public function publishRoster(): void
    {
        $this->ensureRosterEditable();

        // Staff can lay the roster on a Draft, but controllers would be told about an event they can't see.
        if ($this->event->isDraft()) {
            throw ValidationException::withMessages(['roster' => 'Publish the event before its roster.']);
        }

        $this->update(['roster_published_at' => $this->roster_published_at ?? now()]);
        StaffingChanged::forOccurrence($this);
    }

    /**
     * @throws ValidationException
     */
    public function unpublishRoster(): void
    {
        $this->ensureRosterEditable();

        $this->update(['roster_published_at' => null]);
        StaffingChanged::forOccurrence($this);
    }

    /**
     * @throws ValidationException when the event has no roster or it has been archived
     */
    private function ensureRosterEditable(): void
    {
        if ($this->event->staffing_mode !== StaffingMode::OpenInterest) {
            throw ValidationException::withMessages(['roster' => 'Only open-interest events have a roster.']);
        }

        if ($this->isRosterArchived()) {
            throw ValidationException::withMessages(['roster' => 'The event has ended, so its roster is archived.']);
        }
    }
}
