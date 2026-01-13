<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staffing Model
 *
 * Represents a controller staffing sheet for a specific event instance.
 * Contains position definitions and links to Discord announcement messages.
 *
 * @property int $id
 * @property int $event_instance_id
 * @property string|null $description Staffing requirements description
 * @property string|null $channel_id Discord channel ID where message is posted
 * @property string|null $message_id Discord message ID for live updates
 * @property string|null $section_1_title Title for position section 1
 * @property string|null $section_2_title Title for position section 2
 * @property string|null $section_3_title Title for position section 3
 * @property string|null $section_4_title Title for position section 4
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
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
     * Get the specific event instance (occurrence) this staffing belongs to.
     *
     * A staffing is always tied to one specific date/time occurrence,
     * not to the parent event template.
     *
     * @return BelongsTo
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(EventInstance::class, 'event_instance_id');
    }

    /**
     * Get the parent event through the instance relationship.
     *
     * Convenience method to access event details without loading the instance first.
     *
     * @return BelongsTo
     */
    public function event()
    {
        return $this->instance->event();
    }

    /**
     * Get all positions (controller slots) for this staffing.
     *
     * Positions represent specific ATC positions that can be booked.
     *
     * @return HasMany
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Get the event title through relationships.
     *
     * Safely retrieves the event title with fallback for orphaned records.
     *
     * @return string
     */
    public function getEventTitleAttribute(): string
    {
        return $this->instance?->event?->title ?? 'Unknown Event';
    }
}