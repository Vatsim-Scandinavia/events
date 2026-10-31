<?php

namespace App\Integrations\Discord;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Event's Roster channel webhook, and the Roster message the Hub posted.
 *
 * The event form only ever writes the webhook wanted (null once removed) and its channel;
 * UpdateRosterMessage alone writes the message: which webhook posted it and its id. So a
 * replaced webhook's message stays known until it's deleted in Discord, and a form save
 * can't race the job over the same columns.
 *
 * @property int $event_id
 * @property ?string $webhook
 * @property ?string $channel_id Null until Discord confirms it.
 * @property ?string $webhook_name
 * @property ?string $posted_webhook
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
    protected $fillable = ['event_id', 'webhook', 'channel_id', 'webhook_name', 'posted_webhook', 'message_id', 'rendered_hash', 'failure'];

    /**
     * Anyone holding a webhook can post to its channel, so they're encrypted at rest.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'webhook' => 'encrypted',
            'posted_webhook' => 'encrypted',
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
