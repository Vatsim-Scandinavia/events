<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * EventInstance Model
 *
 * Represents a specific occurrence (date/time) of an event.
 * For single events, there's one instance.
 * For recurring events, there are multiple instances across the date range.
 *
 * Soft deletion allows manual removal of specific occurrences without
 * affecting other instances in a recurring series.
 *
 * @property int $id
 * @property int $event_id
 * @property \Carbon\Carbon $start_time When this occurrence starts
 * @property \Carbon\Carbon $end_time When this occurrence ends
 * @property \Carbon\Carbon|null $deleted_at Soft delete (manually removed date)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the parent event template.
     *
     * The event contains metadata like title, description, and recurrence rules.
     *
     * @return BelongsTo
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the staffing sheet attached to this specific occurrence.
     *
     * Not all instances have staffing - only those that need controller bookings.
     *
     * @return HasOne
     */
    public function staffing(): HasOne
    {
        return $this->hasOne(Staffing::class, 'event_instance_id');
    }

    /**
     * Scope to filter instances that haven't ended yet.
     *
     * Includes currently running and future instances.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming($query)
    {
        return $query->where('end_time', '>=', now());
    }
}