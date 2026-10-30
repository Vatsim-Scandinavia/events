<?php

namespace App\Integrations\Discord;

use App\Events\EventPublished;
use App\Integrations\Feature;
use App\Integrations\Integrations;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Services\EventBanners;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Announces each Event in the Hub-wide events channel once, when it's first published.
 * Discord being down or slow never stops staff publishing: failures are logged and skipped.
 */
class EventAnnouncements extends Feature
{
    /** How many upcoming dates to list before "and N more". */
    private const MAX_DATES = 5;

    public function key(): string
    {
        return 'announcements';
    }

    public function name(): string
    {
        return 'Event announcements';
    }

    public function description(): string
    {
        return 'Posts each event in the events channel once, when it’s published.';
    }

    public function boot(): void
    {
        Events::listen(EventPublished::class, function (EventPublished $published) {
            if (! $this->isOn()) {
                return;
            }

            $this->announce($published->event);
        });
    }

    private function announce(Event $event): void
    {
        $webhook = (string) app(Integrations::class)->configuration(app(Discord::class))->get('events_webhook');

        if (blank($webhook)) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, [
                'embeds' => [$this->embed($event)],
                // Event names are typed by staff, so never let them ping anyone.
                'allowed_mentions' => ['parse' => []],
            ])->throw();
        } catch (ConnectionException|RequestException $e) {
            // Connection errors name the URL they called, and the webhook URL is a secret.
            Log::warning('Could not announce the event on Discord.', [
                'event' => $event->id,
                'exception' => str_replace($webhook, '[webhook]', $e->getMessage()),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function embed(Event $event): array
    {
        $embed = [
            'title' => $event->name,
            'url' => url("/events/{$event->id}"),
            'fields' => array_values(array_filter([
                $event->fir ? ['name' => 'FIR', 'value' => "{$event->fir->name} ({$event->fir->icao})", 'inline' => true] : null,
                ['name' => 'Dates (UTC)', 'value' => $this->dates($event), 'inline' => false],
            ])),
        ];

        if ($banner = app(EventBanners::class)->url($event->banner_path)) {
            $embed['image'] = ['url' => $banner];
        }

        return $embed;
    }

    /**
     * The dates still to come that aren't cancelled, one per line. An event that runs past
     * midnight UTC shows the day it ends too.
     */
    private function dates(Event $event): string
    {
        $upcoming = $event->occurrences()->orderBy('starts_at')->get()
            ->reject(fn (EventOccurrence $occurrence) => $occurrence->hasEnded() || $occurrence->isCancelled());

        $lines = $upcoming->take(self::MAX_DATES)->map(function (EventOccurrence $occurrence) {
            $startsAt = $occurrence->starts_at->utc();
            $endsAt = $occurrence->ends_at->utc();

            return $startsAt->format('D j M Y, H:i').'–'.$endsAt->format($endsAt->isSameDay($startsAt) ? 'H:i' : 'D j M H:i').'z';
        });

        if ($upcoming->count() > self::MAX_DATES) {
            $lines->push('and '.($upcoming->count() - self::MAX_DATES).' more');
        }

        return $lines->implode("\n") ?: 'No upcoming dates.';
    }

    private function isOn(): bool
    {
        return app(Integrations::class)->isOn(app(Discord::class), $this);
    }
}
