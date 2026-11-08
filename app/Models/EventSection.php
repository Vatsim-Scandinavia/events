<?php

namespace App\Models;

use Database\Factories\EventSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Section: a labelled break in an Event's list of positions, grouping the positions
 * below it until the next Section. Its sort shares one numbering with the positions.
 */
class EventSection extends Model
{
    /** @use HasFactory<EventSectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['label', 'sort'];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
