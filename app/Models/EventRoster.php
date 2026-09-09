<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EventRosterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $event_id
 * @property string $mode
 * @property bool $is_open
 * @property CarbonImmutable|null $opened_at
 * @property Event $event
 * @property Collection<int, RosterShift> $shifts
 * @property Collection<int, RosterPosition> $positions
 * @property Collection<int, RosterInterest> $interests
 * @property Collection<int, RosterBooking> $bookings
 */
#[Fillable(['event_id', 'mode', 'is_open', 'opened_at'])]
class EventRoster extends Model
{
    /** @use HasFactory<EventRosterFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<RosterShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(RosterShift::class, 'roster_id')->orderBy('id');
    }

    /** @return HasManyThrough<RosterSlot, RosterShift, $this> */
    public function slots(): HasManyThrough
    {
        return $this->hasManyThrough(RosterSlot::class, RosterShift::class, 'roster_id', 'shift_id');
    }

    /** @return HasMany<RosterPosition, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(RosterPosition::class, 'roster_id')->orderBy('id');
    }

    /** @return HasMany<RosterInterest, $this> */
    public function interests(): HasMany
    {
        return $this->hasMany(RosterInterest::class, 'roster_id')->orderBy('id');
    }

    /** @return HasMany<RosterBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(RosterBooking::class, 'roster_id')->orderBy('id');
    }

    /** @param Builder<EventRoster> $query */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('event_id', Event::visibleTo($user)->select('events.id'))
            ->when($user->controllerTeams()->exists(), fn (Builder $query) => $query->orWhereNotNull('opened_at')));
    }

    /** @return array<string, mixed> */
    public function auditValues(): array
    {
        $this->load(['shifts.slots', 'positions', 'interests', 'bookings']);

        return [
            ...$this->only(['event_id', 'mode', 'is_open']),
            'shifts' => $this->shifts->map(fn (RosterShift $shift): array => [
                'id' => $shift->id, 'name' => $shift->name,
                'slots' => $shift->slots->map(fn (RosterSlot $slot): array => [
                    'id' => $slot->id, 'callsign' => $slot->callsign,
                    ...$slot->only(['start_day_offset', 'start_time', 'end_day_offset', 'end_time']),
                ])->all(),
            ])->all(),
            'positions' => $this->positions->map->only(['id', 'callsign'])->all(),
            'bookings' => $this->bookings->map(fn (RosterBooking $booking): array => [
                ...$booking->only(['id', 'slot_id', 'user_cid', 'occurrence_date', 'callsign', 'shift_name']),
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
            ])->all(),
            'interests' => $this->interests->map->only(['id', 'user_cid', 'occurrence_date', 'position_ids', 'position_callsigns', 'availability'])->all(),
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'opened_at' => 'immutable_datetime'];
    }
}
