<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EventCollaborationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int $team_id
 * @property CarbonImmutable|null $accepted_at
 * @property Event $event
 * @property Team $team
 */
#[Fillable(['team_id', 'accepted_at'])]
class EventCollaboration extends Model
{
    /** @use HasFactory<EventCollaborationFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }
}
