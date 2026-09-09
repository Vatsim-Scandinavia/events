<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RosterSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shift_id
 * @property string $callsign
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int|null $booked_by
 * @property RosterShift $shift
 * @property User|null $controller
 */
#[Fillable(['shift_id', 'callsign', 'starts_at', 'ends_at', 'booked_by'])]
class RosterSlot extends Model
{
    /** @use HasFactory<RosterSlotFactory> */
    use HasFactory;

    /** @return BelongsTo<RosterShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(RosterShift::class, 'shift_id');
    }

    /** @return BelongsTo<User, $this> */
    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by', 'cid');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'booked_by' => 'integer'];
    }
}
