<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A controller's claim on one shift for one occurrence.
 */
class ShiftBooking extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['position_shift_id', 'event_occurrence_id', 'user_id'];

    /**
     * @return BelongsTo<PositionShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PositionShift::class, 'position_shift_id');
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
