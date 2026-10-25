<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A controller position staff want covered during an event, e.g. EKCH_TWR.
 */
class EventPosition extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['callsign', 'frequency', 'sort'];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<PositionShift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(PositionShift::class)->orderBy('starts_offset');
    }
}
