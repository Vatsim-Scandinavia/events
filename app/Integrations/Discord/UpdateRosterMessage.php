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
 * Brings one Event's Roster message in line with its staffing as it is when the job runs:
 * posts it, edits it, or deletes it once the Event is gone. Changes made while one is waiting
 * are picked up by it, so a burst of bookings is one edit; jobs for the same Event never run at
 * once, so an older edit can't land after a newer one.
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

        $webhook = new Webhook($message->webhook);
        $event = Event::query()->find($this->eventId);

        try {
            if ($event === null) {
                if ($message->message_id !== null) {
                    $webhook->delete($message->message_id);
                }

                $message->delete();

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

            if ($message->message_id === null || ! $webhook->edit($message->message_id, $content)) {
                $message->message_id = $webhook->post($content);
            }

            $message->fill(['rendered_hash' => $hash, 'failure' => null])->save();
        } catch (WebhookRefused $refused) {
            $message->update(['failure' => $refused->getMessage()]);
        } catch (ConnectionFailed $failed) {
            $message->update(['failure' => $failed->getMessage()]);

            throw $failed;
        }
    }
}
