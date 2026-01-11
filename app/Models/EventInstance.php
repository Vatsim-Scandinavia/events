<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected $dates = ['deleted_at'];

    /**
     * Relationship back to the parent "Template"
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Relationship to the staffing roster
     */
    public function staffing(): HasOne
    {
        return $this->hasOne(Staffing::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('end_time', '>=', now());
    }
}
