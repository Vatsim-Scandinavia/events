<?php

namespace App\Integrations\Discord;

use App\Integrations\ConnectionFailed;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Brings one Event's Roster message in line with its staffing and webhook as they are when the
 * job runs: posts it, edits it, or deletes it once its webhook is replaced or removed or the
 * Event is gone. Changes made while one is waiting are picked up by it, so a burst of bookings
 * is one edit; jobs for the same Event never run at once, so an older edit can't land after a
 * newer one. It's the only thing that writes which message was posted (see RosterMessage).
 */
class UpdateRosterMessage implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /** How long to wait for more changes before editing. */
    public const int COALESCE_SECONDS = 5;

    public int $tries = 5;

    /** Discord is down or rate-limiting: wait longer each time. */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public int $eventId) {}

    /**
     * Queue it once the change that caused it is committed. Discord being down never fails
     * that change, even where the queue runs jobs straight away: the queue or the sweep tries
     * again.
     */
    public static function soon(int $eventId): void
    {
        DB::afterCommit(function () use ($eventId) {
            try {
                static::dispatch($eventId)->delay(self::COALESCE_SECONDS);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->eventId))->releaseAfter(10)->expireAfter(120)];
    }

    /**
     * @throws ConnectionFailed to try again later
     */
    public function handle(): void
    {
        $feature = app(Discord::class)->feature('roster');
        $message = RosterMessage::query()->where('event_id', $this->eventId)->first();

        if (! $feature instanceof RosterMessages || ! $feature->isOn() || $message === null) {
            return;
        }

        $event = Event::query()->find($this->eventId);

        try {
            // The old message goes before anything new is posted, so the channel never shows two.
            if ($message->message_id !== null && ($event === null || $message->posted_webhook !== $message->webhook)) {
                $this->deletePosted($message);
            }

            if ($event === null) {
                $message->delete();

                return;
            }

            if ($message->webhook === null) {
                // Unless a new webhook was set on the Event meanwhile.
                RosterMessage::query()->whereKey($message->id)->whereNull('webhook')->delete();

                return;
            }

            // Nothing is shown before the Event is public.
            if ($event->isDraft()) {
                return;
            }

            $content = RosterMessageContent::for($event);
            $hash = hash('sha256', (string) json_encode($content));

            if ($message->message_id !== null && $message->rendered_hash === $hash && $message->failure === null) {
                return;
            }

            $webhook = new Webhook($message->webhook);

            if ($message->message_id === null || ! $webhook->edit($message->message_id, $content)) {
                $posted = $webhook->post($content);
                $message->fill(['posted_webhook' => $message->webhook, 'message_id' => $posted['id']]);

                // A webhook saved while Discord was down learns its channel from its first post.
                RosterMessage::query()->whereKey($message->id)->whereNull('channel_id')
                    ->update(['channel_id' => $posted['channel_id'], 'webhook_name' => $posted['name']]);
            }

            $message->fill(['rendered_hash' => $hash, 'failure' => null])->save();
        } catch (WebhookRefused $refused) {
            $message->update(['failure' => $refused->getMessage()]);
        } catch (ConnectionFailed $failed) {
            $message->update(['failure' => $failed->getMessage()]);

            throw $failed;
        }
    }

    /**
     * Deletes the message through the webhook that posted it. One Discord no longer knows, or
     * won't let delete it, leaves nothing more to try.
     *
     * @throws ConnectionFailed
     */
    private function deletePosted(RosterMessage $message): void
    {
        try {
            (new Webhook((string) $message->posted_webhook))->delete((string) $message->message_id);
        } catch (WebhookRefused) {
            //
        }

        $message->fill(['posted_webhook' => null, 'message_id' => null, 'rendered_hash' => null])->save();
    }
}
