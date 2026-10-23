<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A controller's interest in staffing an occurrence: the positions they want and
 * the windows they're available. Only the controller and Event Staff may see it.
 */
class StaffingInterest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'notes'];

    /**
     * @return HasMany<StaffingInterestWindow, $this>
     */
    public function windows(): HasMany
    {
        return $this->hasMany(StaffingInterestWindow::class)->orderBy('starts_at');
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

    /**
     * @return BelongsToMany<EventPosition, $this>
     */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(EventPosition::class)->orderBy('sort')->orderBy('event_positions.id');
    }
}
