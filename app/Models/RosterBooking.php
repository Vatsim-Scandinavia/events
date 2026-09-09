<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RosterBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $roster_id
 * @property int|null $slot_id
 * @property int $user_cid
 * @property string $occurrence_date
 * @property string $callsign
 * @property string $shift_name
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property EventRoster $roster
 * @property RosterSlot|null $slot
 * @property User $user
 */
#[Fillable(['roster_id', 'slot_id', 'user_cid', 'occurrence_date', 'callsign', 'shift_name', 'starts_at', 'ends_at'])]
class RosterBooking extends Model
{
    /** @use HasFactory<RosterBookingFactory> */
    use HasFactory;

    /** @return BelongsTo<EventRoster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(EventRoster::class, 'roster_id');
    }

    /** @return BelongsTo<RosterSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(RosterSlot::class, 'slot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_cid', 'cid');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'user_cid' => 'integer'];
    }
}
