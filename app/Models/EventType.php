<?php

namespace App\Models;

use Database\Factories\EventTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of event, such as a Fly-in, from the list admins manage. Events without one show as General.
 */
class EventType extends Model
{
    /** @use HasFactory<EventTypeFactory> */
    use HasFactory;

    /**
     * Shown for events that have no type, including those whose type was deleted.
     */
    public const string FALLBACK = 'General';

    /**
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
