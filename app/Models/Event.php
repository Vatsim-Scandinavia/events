<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\RepeatInterval;
use App\Enums\StaffingMode;
use App\Services\EventBanners;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fir_id',
        'name',
        'event_type_id',
        'description',
        'airports',
        'routes',
        'starts_at',
        'ends_at',
        'timezone',
        'repeats',
        'repeat_count',
        'repeat_until',
        'cancelled_at',
        'banner_path',
        'booking_url',
        'briefing_url',
        'staffing_mode',
        'interest_opens_hours',
        'interest_closes_hours',
    ];

    /**
     * Default values for attributes the database also defaults, so new models carry them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'staffing_mode' => 'none',
        'repeats' => 'none',
        'timezone' => 'UTC',
        'interest_opens_hours' => 336,
        'interest_closes_hours' => 24,
    ];

    /**
     * Routes are stored as a list of {from, to, route}.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'airports' => 'array',
            'routes' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'repeats' => RepeatInterval::class,
            'repeat_count' => 'integer',
            'repeat_until' => 'immutable_date',
            'cancelled_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'staffing_mode' => StaffingMode::class,
            'interest_opens_hours' => 'integer',
            'interest_closes_hours' => 'integer',
        ];
    }

    /**
     * Every event has at least one occurrence: the one on its own dates. Deleting an
     * event removes its banner file.
     */
    protected static function booted(): void
    {
        static::created(function (Event $event) {
            $event->occurrences()->create([
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
            ]);
        });

        static::deleted(fn (Event $event) => app(EventBanners::class)->delete($event->banner_path));
    }

    /**
     * The FIR that runs the event.
     *
     * @return BelongsTo<Fir, $this>
     */
    public function fir(): BelongsTo
    {
        return $this->belongsTo(Fir::class);
    }

    /**
     * The kind of event. Without one, it shows as General.
     *
     * @return BelongsTo<EventType, $this>
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /**
     * @return HasMany<EventPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(EventPosition::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<EventOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        // Occurrences read the event to tell whether they're cancelled, so hand it to them.
        return $this->hasMany(EventOccurrence::class)->orderBy('starts_at')->chaperone();
    }

    /**
     * The occurrence a visitor most likely cares about: the next one that hasn't ended
     * and isn't cancelled, else the next one that hasn't ended, or the most recent one
     * once they all have.
     */
    public function currentOccurrence(): ?EventOccurrence
    {
        $occurrences = $this->occurrences;
        $upcoming = $occurrences->reject(fn (EventOccurrence $occurrence) => $occurrence->hasEnded());

        return $upcoming->first(fn (EventOccurrence $occurrence) => ! $occurrence->isCancelled())
            ?? $upcoming->first()
            ?? $occurrences->last();
    }

    /**
     * Whether the event is still a Draft: visible only to staff who can view or manage events
     * in its FIR, until it's published.
     */
    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * Makes a Draft visible to everyone. Publishing is one-way, and publishing again keeps
     * when it was first published, even when two requests publish at once.
     *
     * @return bool whether this call published it, rather than it already being published
     */
    public function publish(): bool
    {
        $published = static::query()->whereKey($this->getKey())->whereNull('published_at')->update(['published_at' => now()]) > 0;

        $this->forceFill(['published_at' => static::query()->whereKey($this->getKey())->value('published_at')])->syncOriginal();

        return $published;
    }

    /**
     * Events that aren't Drafts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * Events the user may see: published ones, plus Drafts in FIRs where they can view or
     * manage events. Guests only see published events.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        if ($user === null) {
            $query->published();

            return;
        }

        $firs = $user->firIdsWithAny(Permission::ManageEvents, Permission::ViewEvents);

        // Held across the hub, so every Draft.
        if ($firs === null) {
            return;
        }

        $query->where(fn (Builder $query) => $query->whereNotNull('published_at')->orWhereIn('fir_id', $firs));
    }

    /**
     * Whether staff called off the whole event. Its dates count as cancelled too.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Calls off the whole event. Nothing is deleted: bookings, interest and rosters stay so a
     * restored event picks up where it was.
     *
     * @throws ValidationException when every date is already over
     */
    public function cancel(): void
    {
        if ($this->occurrences->every(fn (EventOccurrence $occurrence) => $occurrence->hasEnded())) {
            throw ValidationException::withMessages(['cancel' => 'All of this event’s dates are already over.']);
        }

        $this->update(['cancelled_at' => $this->cancelled_at ?? now()]);
    }

    public function restore(): void
    {
        $this->update(['cancelled_at' => null]);
    }

    /**
     * Switches how the event is staffed. Data that belongs to the old mode is cleared, so it
     * can't resurface if staff switch back later:
     *
     * - Shifts and their bookings.
     * - Interest and rosters for dates that haven't ended (rosters of past dates stay as a record).
     * - Positions too, when staffing is switched off.
     */
    public function switchStaffingMode(StaffingMode $mode): void
    {
        if ($this->staffing_mode === $mode) {
            return;
        }

        DB::transaction(function () use ($mode) {
            PositionShift::query()->whereIn('event_position_id', $this->positions()->select('id'))->delete();

            $upcoming = $this->occurrences()->where('ends_at', '>', now())->pluck('id');
            StaffingInterest::query()->whereIn('event_occurrence_id', $upcoming)->delete();
            RosterEntry::query()->whereIn('event_occurrence_id', $upcoming)->delete();
            $this->occurrences()->whereKey($upcoming)->update(['roster_published_at' => null]);

            if ($mode === StaffingMode::None) {
                $this->positions()->delete();
            }

            $this->update(['staffing_mode' => $mode]);
        });
    }

    /**
     * Length of the event in minutes, which bounds the offsets shifts may use.
     */
    public function durationInMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Events that aren't cancelled with a date that has not finished and isn't cancelled,
     * by that next date.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereNull('cancelled_at')
            ->whereHas('occurrences', fn (Builder $query) => $query->where('ends_at', '>=', now())->whereNull('cancelled_at'))
            ->orderBy(EventOccurrence::query()
                ->select('starts_at')
                ->whereColumn('event_id', 'events.id')
                ->where('ends_at', '>=', now())
                ->whereNull('cancelled_at')
                ->orderBy('starts_at')
                ->limit(1))
            ->with(['occurrences', 'fir', 'eventType']);
    }
}
