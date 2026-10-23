<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Announces published Events on Discord, through one webhook for the whole hub. Discord being
 * down or slow never stops staff publishing: failures are logged and skipped.
 */
final class DiscordAnnouncements
{
    /** How many upcoming dates to list before "and N more". */
    private const MAX_DATES = 5;

    public function __construct(private EventBanners $banners) {}

    /**
     * Posts the event once, when it's published. Does nothing without a webhook.
     */
    public function eventPublished(Event $event): void
    {
        $webhook = config('services.discord.events_webhook');

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
            Log::warning('Could not announce the event on Discord.', ['event' => $event->id, 'exception' => $e->getMessage()]);
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

        if ($banner = $this->banners->url($event->banner_path)) {
            $embed['image'] = ['url' => $banner];
        }

        return $embed;
    }

    /**
     * The dates still to come that aren't cancelled, one per line.
     */
    private function dates(Event $event): string
    {
        $upcoming = $event->occurrences()->orderBy('starts_at')->get()
            ->reject(fn (EventOccurrence $occurrence) => $occurrence->hasEnded() || $occurrence->isCancelled());

        $lines = $upcoming->take(self::MAX_DATES)->map(fn (EventOccurrence $occurrence) => $occurrence->starts_at->utc()->format('D j M Y, H:i')
            .'–'.$occurrence->ends_at->utc()->format('H:i').'z');

        if ($upcoming->count() > self::MAX_DATES) {
            $lines->push('and '.($upcoming->count() - self::MAX_DATES).' more');
        }

        return $lines->implode("\n") ?: 'No upcoming dates.';
    }
}
