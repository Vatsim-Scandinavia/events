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
 * Switched off, it forgets every Event's webhook and leaves the messages as they were.
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
        $message = $event ? RosterMessage::query()->where('event_id', $event->id)->first() : null;

        return [new EventField(
            self::FIELD,
            'Roster channel webhook',
            ['string', 'max:255', 'regex:'.Discord::WEBHOOK_PATTERN],
            secret: true,
            description: 'A webhook on this event’s own Discord channel (Edit Channel → Integrations → Webhooks → Copy Webhook URL). A message there shows the next date’s roster, kept up to date.',
            status: $message ? 'Posts as '.($message->webhook_name ?? 'a webhook')." in channel {$message->channel_id}" : null,
        )];
    }

    public function saveEventFields(Event $event, array $values, array $removed): void
    {
        $message = RosterMessage::query()->where('event_id', $event->id)->first();
        $webhook = $values[self::FIELD] ?? null;

        if ($webhook === null && ! in_array(self::FIELD, $removed, true)) {
            UpdateRosterMessage::soon($event->id);

            return;
        }

        if ($webhook !== null) {
            try {
                $channel = (new Webhook($webhook))->check();
            } catch (ConnectionFailed|WebhookRefused $exception) {
                throw ValidationException::withMessages(['integration_fields.'.self::FIELD => $exception->getMessage()]);
            }
        }

        // Replaced or removed: the old message goes, so the channel never shows two.
        if ($message?->message_id !== null && $message->webhook !== $webhook) {
            DeleteRosterMessage::dispatch($message->webhook, $message->message_id)->afterCommit();
        }

        if ($webhook === null) {
            $message?->delete();

            return;
        }

        if ($message?->webhook !== $webhook) {
            RosterMessage::query()->updateOrCreate(['event_id' => $event->id], [
                'webhook' => $webhook,
                'channel_id' => $channel['channel_id'],
                'webhook_name' => $channel['name'],
                'message_id' => null,
                'rendered_hash' => null,
                'failure' => null,
            ]);
        }

        UpdateRosterMessage::soon($event->id);
    }

    /**
     * Webhooks are kept for this feature's sake only; the messages stay as they were.
     */
    public function switchedOff(): void
    {
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
