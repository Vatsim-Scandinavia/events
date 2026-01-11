<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staffing extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'channel_id',
        'message_id',
        'section_1_title',
        'section_2_title',
        'section_3_title',
        'section_4_title',
        'event_instance_id',
    ];

    /**
     * The specific date/time occurrence this staffing belongs to.
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(EventInstance::class, 'event_instance_id');
    }

    /**
     * Shortcut to the parent Event template.
     */
    public function event()
    {
        return $this->instance->event();
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Clean accessor for the event title.
     */
    public function getEventTitleAttribute()
    {
        return $this->instance?->event?->title ?? 'Unknown Event';
    }
}