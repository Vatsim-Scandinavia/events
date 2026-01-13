<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event Model
 *
 * Represents a VATSIM event which can be either a single occurrence or a recurring series.
 * Events contain metadata and generate EventInstance records for specific dates/times.
 *
 * @property int $id
 * @property int $calendar_id
 * @property int $user_id
 * @property string $title
 * @property string|null $short_description
 * @property string|null $long_description
 * @property int|null $recurrence_interval Number of units between recurrences
 * @property string|null $recurrence_unit Unit of recurrence (day, week, month)
 * @property \Carbon\Carbon|null $recurrence_end_date When recurrence series ends
 * @property bool $published Whether event is visible to public
 * @property string|null $image Banner image filename
 * @property \Carbon\Carbon|null $deleted_at Soft delete timestamp
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'calendar_id', 'title', 'short_description', 'long_description',
        'recurrence_interval', 'recurrence_unit', 'recurrence_end_date',
        'published', 'image', 'user_id',
    ];

    /**
     * Get all instances (occurrences) of this event.
     *
     * For single events, returns one instance.
     * For recurring events, returns multiple instances across the date range.
     *
     * @return HasMany
     */
    public function instances(): HasMany
    {
        return $this->hasMany(EventInstance::class);
    }

    /**
     * Get the next upcoming instance of this event.
     *
     * Returns the closest future instance where end_time hasn't passed.
     * Useful for displaying "Next occurrence" information.
     *
     * @return HasOne
     */
    public function nextInstance(): HasOne
    {
        return $this->hasOne(EventInstance::class)
            ->ofMany(['start_time' => 'min'], function ($query) {
                $query->where('end_time', '>=', now());
            });
    }

    /**
     * Get the staffing record through an event instance.
     *
     * Provides direct access to staffing data for recurring events.
     * Note: A recurring event may have multiple staffings (one per instance).
     *
     * @return HasOneThrough
     */
    public function staffing(): HasOneThrough
    {
        return $this->hasOneThrough(
            Staffing::class,
            EventInstance::class,
            'event_id',
            'event_instance_id',
            'id',
            'id'
        );
    }

    /**
     * Get the user who created this event.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the calendar this event belongs to.
     *
     * @return BelongsTo
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Scope to filter events that have upcoming instances.
     *
     * Only returns events where at least one instance hasn't ended yet.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming($query)
    {
        return $query->whereHas('instances', function ($q) {
            $q->where('start_time', '>=', now());
        });
    }

    /**
     * Gets the appropriate instance to display based on request parameter or next available.
     *
     * Checks for ?instance=X query parameter first, falls back to nextInstance,
     * then falls back to the most recent instance if event has ended.
     *
     * @return EventInstance|null
     */
    public function getDisplayInstance()
    {
        $instanceId = request('instance');

        if ($instanceId) {
            return $this->instances()->find($instanceId);
        }

        return $this->nextInstance ?: $this->instances()->latest('start_time')->first();
    }
}