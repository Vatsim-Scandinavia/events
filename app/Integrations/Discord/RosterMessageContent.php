<?php

namespace App\Integrations\Discord;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\User;
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
        $url = url("/events/{$event->id}");
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
        $startsAt = $occurrence->starts_at->utc();
        $endsAt = $occurrence->ends_at->utc();

        return '**'.$startsAt->format('D j M Y, H:i').'–'.$endsAt->format($endsAt->isSameDay($startsAt) ? 'H:i' : 'D j M H:i').'z**';
    }

    private static function staffing(Event $event, EventOccurrence $occurrence, string $url, int $maxLength): string
    {
        $positions = match ($event->staffing_mode) {
            StaffingMode::Shifts => self::shifts($event, $occurrence),
            StaffingMode::OpenInterest => $occurrence->isRosterPublished() ? self::roster($occurrence) : null,
            default => collect(),
        };

        if ($positions === null) {
            return "Roster not published yet.\n\n[See the event on the Hub]({$url})";
        }

        if ($positions->isEmpty()) {
            return "No positions to staff yet.\n\n[See the event on the Hub]({$url})";
        }

        $link = $event->staffing_mode === StaffingMode::Shifts ? "[Book on the Hub]({$url})" : "[See the roster on the Hub]({$url})";

        return self::fit($positions, $link, $url, $maxLength);
    }

    /**
     * Every shift on the date, booked or open, by position.
     *
     * @return Collection<string, list<string>>
     */
    private static function shifts(Event $event, EventOccurrence $occurrence): Collection
    {
        $bookings = $occurrence->bookings()->with('user')->get()->keyBy('position_shift_id');

        return $event->positions()->with('shifts')->get()
            ->filter(fn (EventPosition $position) => $position->shifts->isNotEmpty())
            ->mapWithKeys(fn (EventPosition $position) => [$position->callsign => $position->shifts
                ->map(fn (PositionShift $shift) => self::line($shift->startsAtFor($occurrence), $shift->endsAtFor($occurrence), $bookings->get($shift->id)?->user))
                ->all()]);
    }

    /**
     * The published Roster Entries, by position.
     *
     * @return Collection<string, list<string>>
     */
    private static function roster(EventOccurrence $occurrence): Collection
    {
        return $occurrence->rosterEntries()->with(['position', 'user'])->get()
            ->sortBy([
                fn (RosterEntry $a, RosterEntry $b) => [$a->position->sort, $a->position->id] <=> [$b->position->sort, $b->position->id],
                fn (RosterEntry $a, RosterEntry $b) => $a->starts_at <=> $b->starts_at,
            ])
            ->groupBy(fn (RosterEntry $entry) => $entry->position->callsign)
            ->map(fn (Collection $entries) => $entries->map(fn (RosterEntry $entry) => self::line($entry->starts_at, $entry->ends_at, $entry->user))->all());
    }

    private static function line(CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?User $controller): string
    {
        $span = $startsAt->utc()->format('H:i').'–'.$endsAt->utc()->format('H:i').'z';

        return $controller ? "`{$span}` ".self::plain($controller->name)." ({$controller->vatsim_cid})" : "`{$span}` *open*";
    }

    /**
     * Positions with their lines, cut short with a link to the Hub when it's too long for Discord.
     *
     * @param  Collection<string, list<string>>  $positions
     * @param  int  $maxLength  What's left of Discord's limit after the date.
     */
    private static function fit(Collection $positions, string $link, string $url, int $maxLength): string
    {
        $total = $positions->sum(fn (array $lines) => count($lines));
        $shown = 0;
        $text = '';

        foreach ($positions as $callsign => $lines) {
            $heading = ($text === '' ? '' : "\n").'**'.self::plain($callsign)."**\n";

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

    /**
     * Names and callsigns are shown as typed, not as Discord markdown, so none can make a link.
     */
    private static function plain(?string $text): string
    {
        return (string) preg_replace('/([\\\\*_`~|>\[\]()#-])/', '\\\\$1', (string) $text);
    }
}
