<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bookable slice of a position. Offsets are minutes from the event's start, so a
 * shift applies to every occurrence of the event.
 */
class PositionShift extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['event_position_id', 'starts_offset', 'ends_offset'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_offset' => 'integer',
            'ends_offset' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EventPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(EventPosition::class, 'event_position_id');
    }

    /**
     * @return HasMany<ShiftBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ShiftBooking::class);
    }

    public function startsAtFor(EventOccurrence $occurrence): CarbonImmutable
    {
        return $occurrence->starts_at->addMinutes($this->starts_offset);
    }

    public function endsAtFor(EventOccurrence $occurrence): CarbonImmutable
    {
        return $occurrence->starts_at->addMinutes($this->ends_offset);
    }
}
