<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventInstance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function staffing(): HasOne
    {
        return $this->hasOne(Staffing::class, 'event_instance_id');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('end_time', '>=', now());
    }
}