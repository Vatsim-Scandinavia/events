<?php

namespace App\Integrations\Discord;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Event's Roster channel webhook, and the Roster message the Hub posted through it.
 *
 * @property int $event_id
 * @property string $webhook
 * @property string $channel_id
 * @property ?string $webhook_name
 * @property ?string $message_id
 * @property ?string $rendered_hash
 * @property ?string $failure
 */
class RosterMessage extends Model
{
    protected $table = 'discord_roster_messages';

    /**
     * @var list<string>
     */
    protected $fillable = ['event_id', 'webhook', 'channel_id', 'webhook_name', 'message_id', 'rendered_hash', 'failure'];

    /**
     * Anyone holding the webhook can post to the channel, so it's encrypted at rest.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'webhook' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
