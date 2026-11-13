<?php

namespace App\Integrations\Discord;

use App\Enums\RosterState;
use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventSection;
use App\Models\User;
use App\Services\DisplayTime;
use App\Staffing\Roster;
use App\Staffing\RosterItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What an Event's Roster message says: its next upcoming date that isn't cancelled, and who
 * works which position then. Never more than the public Event page shows.
 */
final class RosterMessageContent
{
    /** Discord's limit on an embed's description. */
    private const int MAX_LENGTH = 4096;

    /**
     * @return array<string, mixed>
     */
    public static function for(Event $event): array
    {
        $url = route('events.show', $event);
        $occurrence = $event->isCancelled() ? null : $event->occurrences()
            ->whereNull('cancelled_at')
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        $description = match (true) {
            $event->isCancelled() => 'Event cancelled.',
            $occurrence === null => 'No upcoming dates.',
            default => self::date($occurrence)."\n\n".self::staffing($event, $occurrence, $url, self::MAX_LENGTH - mb_strlen(self::date($occurrence)) - 2),
        };

        return [
            'content' => null,
            'embeds' => [[
                'title' => $event->name,
                'url' => $url,
                'description' => $description,
            ]],
            // Event and controller names are typed by people, so never let them ping anyone.
            'allowed_mentions' => ['parse' => []],
        ];
    }

    private static function date(EventOccurrence $occurrence): string
    {
        return '**'.(new DisplayTime)->span($occurrence->starts_at, $occurrence->ends_at).'**';
    }

    private static function staffing(Event $event, EventOccurrence $occurrence, string $url, int $maxLength): string
    {
        $roster = Roster::of($occurrence);

        if ($roster->state() === RosterState::Unpublished) {
            return "Roster not published yet.\n\n[See the event on the Hub]({$url})";
        }

        if ($roster->items()->isEmpty()) {
            return "No positions to staff yet.\n\n[See the event on the Hub]({$url})";
        }

        $link = $event->staffing_mode === StaffingMode::Shifts ? "[Book on the Hub]({$url})" : "[See the roster on the Hub]({$url})";
        $byCallsign = $roster->items()->groupBy(fn (RosterItem $item) => $item->position->callsign);
        $positions = $byCallsign->map(fn (Collection $items) => $items->map(fn (RosterItem $item) => self::line($item->startsAt, $item->endsAt, $item->controller()))->all());
        $sections = $byCallsign->map(fn (Collection $items) => $items->first()->section)->all();

        return self::fit($positions, $sections, $link, $url, $maxLength);
    }

    private static function line(CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?User $controller): string
    {
        $span = (new DisplayTime)->range($startsAt, $endsAt);

        return $controller ? "`{$span}` ".Discord::plain($controller->displayName()).($controller->showsCidBesideName() ? " ({$controller->vatsim_cid})" : '') : "`{$span}` *open*";
    }

    /**
     * Positions with their lines, cut short with a link to the Hub when it's too long for Discord.
     * A Section's label goes above its first position shown; one without a label is a rule.
     *
     * @param  Collection<string, list<string>>  $positions
     * @param  array<string, EventSection|null>  $sections  by callsign
     * @param  int  $maxLength  What's left of Discord's limit after the date.
     */
    private static function fit(Collection $positions, array $sections, string $link, string $url, int $maxLength): string
    {
        $total = $positions->sum(fn (array $lines) => count($lines));
        $shown = 0;
        $text = '';
        $currentSection = null;

        foreach ($positions as $callsign => $lines) {
            $section = $sections[$callsign] ?? null;
            $heading = ($text === '' ? '' : "\n");

            // A rule at the very top would separate nothing, so it's left out.
            if ($section !== null && $section->id !== $currentSection?->id && ($section->label !== null || $text !== '')) {
                $heading .= ($section->label !== null ? '__**'.Discord::plain($section->label).'**__' : '───────────')."\n";
            }

            $currentSection = $section;
            $heading .= '**'.Discord::plain($callsign)."**\n";

            foreach ($lines as $index => $line) {
                $addition = ($index === 0 ? $heading : '').$line."\n";
                $more = '…and '.($total - $shown).' more. [See the Hub]('.$url.')';

                if (mb_strlen($text.$addition."\n".$more) > $maxLength) {
                    return $text."\n".$more;
                }

                $text .= $addition;
                $shown++;
            }
        }

        return $text."\n".$link;
    }
}
