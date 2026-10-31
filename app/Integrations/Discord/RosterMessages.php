<?php

namespace App\Integrations\Discord;

use App\Events\EventField;
use App\Events\EventFields;
use App\Integrations\ConnectionFailed;
use App\Integrations\Feature;
use App\Integrations\Integrations;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Staffing\StaffingChanged;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Validation\ValidationException;

/**
 * Keeps one Roster message in each Event's Roster channel, showing the Roster of its next
 * upcoming date that isn't cancelled (see ADR 0005). Whenever staffing changes, the Event's
 * message is brought in line (UpdateRosterMessage); a sweep every few minutes moves messages on
 * once their date ends and catches anything missed.
 *
 * Switched off, it deletes the messages and forgets every Event's webhook.
 */
class RosterMessages extends Feature implements EventFields
{
    public const string FIELD = 'discord_roster_webhook';

    public function key(): string
    {
        return 'roster';
    }

    public function name(): string
    {
        return 'Roster messages';
    }

    public function description(): string
    {
        return 'Keeps a message with the next date’s roster in each event’s own channel, using a webhook set on the event.';
    }

    /**
     * The sweep: it isn't a sync, so it doesn't record runs every five minutes.
     */
    public function scheduled(Schedule $schedule): void
    {
        $schedule->call(fn () => $this->sweep())
            ->name('integrations:discord.roster')
            ->everyFiveMinutes()
            ->when(fn () => $this->isOn());
    }

    public function boot(): void
    {
        Events::listen(StaffingChanged::class, function (StaffingChanged $changed) {
            if (! $this->isOn()) {
                return;
            }

            $eventIds = EventOccurrence::query()->whereKey($changed->occurrenceIds)->distinct()->pluck('event_id')
                // A deleted Event's dates are gone, but its message is still there to delete.
                ->merge(RosterMessage::query()->whereDoesntHave('event')->pluck('event_id'));

            foreach ($eventIds->unique() as $eventId) {
                UpdateRosterMessage::soon($eventId);
            }
        });
    }

    /**
     * Queue every message to be brought in line. Nothing is sent from here, and a message that
     * hasn't changed isn't edited.
     */
    public function sweep(): void
    {
        foreach (RosterMessage::query()->pluck('event_id') as $eventId) {
            UpdateRosterMessage::soon($eventId);
        }
    }

    public function eventFields(?Event $event): array
    {
        $message = $event ? RosterMessage::query()->where('event_id', $event->id)->whereNotNull('webhook')->first() : null;

        return [new EventField(
            self::FIELD,
            'Roster channel webhook',
            ['string', 'max:255', 'regex:'.Discord::WEBHOOK_PATTERN],
            secret: true,
            description: 'A webhook on this event’s own Discord channel (Edit Channel → Integrations → Webhooks → Copy Webhook URL). A message there shows the next date’s roster, kept up to date.',
            status: match (true) {
                $message === null => null,
                $message->channel_id === null => 'Saved, but not checked yet: Discord couldn’t be reached. The message is posted once it can be.',
                default => 'Posts as '.($message->webhook_name ?? 'a webhook')." in channel {$message->channel_id}",
            },
        )];
    }

    /**
     * Only says which webhook is wanted; UpdateRosterMessage deletes a replaced one's message
     * and posts through the new one. A webhook Discord doesn't know is turned down, but one
     * that can't be checked while Discord is down is kept, and the post tells where it goes.
     */
    public function saveEventFields(Event $event, array $values, array $removed): void
    {
        $message = RosterMessage::query()->where('event_id', $event->id)->first();
        $webhook = $values[self::FIELD] ?? null;

        if ($webhook === null && in_array(self::FIELD, $removed, true)) {
            $message?->update(['webhook' => null, 'channel_id' => null, 'webhook_name' => null]);
        }

        if ($webhook !== null && $message?->webhook !== $webhook) {
            try {
                $channel = (new Webhook($webhook))->check();
            } catch (WebhookRefused $refused) {
                throw ValidationException::withMessages(['integration_fields.'.self::FIELD => $refused->getMessage()]);
            } catch (ConnectionFailed) {
                $channel = ['channel_id' => null, 'name' => null];
            }

            RosterMessage::query()->updateOrCreate(['event_id' => $event->id], [
                'webhook' => $webhook,
                'channel_id' => $channel['channel_id'],
                'webhook_name' => $channel['name'],
            ]);
        }

        UpdateRosterMessage::soon($event->id);
    }

    /**
     * Messages that would no longer be kept up to date are deleted, and the webhooks forgotten.
     */
    public function switchedOff(): void
    {
        foreach (RosterMessage::query()->whereNotNull('message_id')->get() as $message) {
            DeleteRosterMessage::dispatch((string) $message->posted_webhook, (string) $message->message_id)->afterCommit();
        }

        RosterMessage::query()->delete();
    }

    public function needsAttention(): array
    {
        return RosterMessage::query()
            ->whereNotNull('failure')
            ->with('event')
            ->get()
            ->map(fn (RosterMessage $message) => ($message->event->name ?? "Deleted event {$message->event_id}").": roster message not updated. {$message->failure}")
            ->all();
    }

    public function isOn(): bool
    {
        return app(Integrations::class)->isOn(app(Discord::class), $this);
    }
}
