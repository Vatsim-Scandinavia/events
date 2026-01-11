<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'calendar_id', 'title', 'short_description', 'long_description',
        'recurrence_interval', 'recurrence_unit', 'recurrence_end_date',
        'published', 'image', 'user_id',
    ];

    /**
     * Relationship to all specific occurrences.
     */
    public function instances(): HasMany
    {
        return $this->hasMany(EventInstance::class);
    }

    /**
     * Gets the single closest upcoming instance.
     */
    public function nextInstance(): HasOne
    {
        return $this->hasOne(EventInstance::class)
            ->ofMany(['start_time' => 'min'], function ($query) {
                $query->where('end_time', '>=', now());
            });
    }

    /**
     * Access the staffing sheet directly via the instances.
     */
    public function staffing(): HasOneThrough
    {
        return $this->hasOneThrough(
            Staffing::class,
            EventInstance::class,
            'event_id',          // Foreign key on EventInstance
            'event_instance_id', // Foreign key on Staffing
            'id',                // Local key on Event
            'id'                 // Local key on EventInstance
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereHas('instances', function ($q) {
            $q->where('start_time', '>=', now());
        });
    }

    public function getDisplayInstance()
    {
        $instanceId = request('instance');

        if ($instanceId) {
            return $this->instances()->find($instanceId);
        }

        return $this->nextInstance ?: $this->instances()->latest('start_time')->first();
    }
}