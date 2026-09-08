<?php

namespace App\Models;

use Database\Factories\EventCancellationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property string $occurrence_date
 * @property string|null $reason
 */
#[Fillable(['occurrence_date', 'reason'])]
class EventCancellation extends Model
{
    /** @use HasFactory<EventCancellationFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
