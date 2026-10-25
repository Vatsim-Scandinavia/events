<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A controller assigned to a position for part of an occurrence by Event Staff.
 */
class RosterEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['event_position_id', 'user_id', 'starts_at', 'ends_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(EventPosition::class, 'event_position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
