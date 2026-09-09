<?php

namespace App\Models;

use Database\Factories\RosterSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $shift_id
 * @property string $callsign
 * @property int $start_day_offset
 * @property string $start_time
 * @property int $end_day_offset
 * @property string $end_time
 * @property RosterShift $shift
 * @property Collection<int, RosterBooking> $bookings
 */
#[Fillable(['shift_id', 'callsign', 'start_day_offset', 'start_time', 'end_day_offset', 'end_time'])]
class RosterSlot extends Model
{
    /** @use HasFactory<RosterSlotFactory> */
    use HasFactory;

    /** @return BelongsTo<RosterShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(RosterShift::class, 'shift_id');
    }

    /** @return HasMany<RosterBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(RosterBooking::class, 'slot_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_day_offset' => 'integer', 'end_day_offset' => 'integer'];
    }
}
