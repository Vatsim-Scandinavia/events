<?php

namespace App\Services;

use App\Models\Event;

/**
 * What a page tells sites that unfurl its link, such as Discord: Open Graph and description
 * tags in the server-rendered head, since crawlers don't run the app's JavaScript.
 */
final class LinkPreview
{
    /** The home page's tagline, the description of every page without its own. */
    public const string TAGLINE = 'Fly-ins, controller events and group flights in our airspace, all in one place. Book your slot and get the briefing you need before you push back or plug in.';

    public function __construct(private VaccIdentity $identity, private EventBanners $banners) {}

    /**
     * The preview of any page: the Hub itself.
     *
     * @return array{siteName: string, title: string, description: string, url: string, image: string|null, card: string}
     */
    public function site(string $url): array
    {
        $siteName = $this->identity->effective()['name'].' Event Hub';

        return [
            'siteName' => $siteName,
            'title' => $siteName,
            'description' => self::TAGLINE,
            'url' => $url,
            'image' => null,
            'card' => 'summary',
        ];
    }

    /**
     * The preview of an event's page. Without a Summary it says when and where the event
     * next is, in UTC, and without a banner it shows the vACC's logo, unless that's an SVG,
     * which sites that unfurl links don't show.
     *
     * @return array{siteName: string, title: string, description: string, url: string, image: string|null, card: string}
     */
    public function event(Event $event): array
    {
        $next = $event->currentOccurrence();
        $banner = $this->banners->url($event->banner_path);
        $logo = $this->identity->logo();
        $image = $banner ?? ($logo !== null && $logo['type'] !== 'image/svg+xml' ? $this->identity->effective()['logo'] : null);
        // The next date is only a cancelled one once every date left is cancelled.
        $cancelled = $event->isCancelled() || $next?->isCancelled();

        return [
            ...$this->site(route('events.show', $event)),
            'title' => ($cancelled ? 'Cancelled: ' : '').$event->name,
            'description' => $event->summary ?? $this->nextDateAndPlace($event),
            'image' => $image !== null ? url($image) : null,
            'card' => $banner !== null ? 'summary_large_image' : 'summary',
        ];
    }

    /**
     * e.g. "Tue 17 Nov 2026 · 10:00–22:00z · KJFK, EGLL", or with the end's date too when it's
     * another day: "Tue 17 Nov 2026 · 22:00z–Wed 18 Nov 02:00z · EKCH".
     */
    private function nextDateAndPlace(Event $event): string
    {
        $next = $event->currentOccurrence() ?? $event;
        $startsAt = $next->starts_at->utc();
        $endsAt = $next->ends_at->utc();
        $times = $startsAt->isSameDay($endsAt)
            ? $startsAt->format('H:i').'–'.$endsAt->format('H:i').'z'
            : $startsAt->format('H:i').'z–'.$endsAt->format('D j M H:i').'z';

        return implode(' · ', array_filter([
            $startsAt->format('D j M Y'),
            $times,
            implode(', ', $event->airports ?? []),
        ]));
    }
}
