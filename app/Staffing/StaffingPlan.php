<?php

namespace App\Staffing;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\ShiftBooking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves what Event Staff planned on the staffing page in one go: the positions, and
 * either their shifts or the occurrence's roster. Nothing changes until staff press
 * save, so controllers never see (or book) a half-finished plan.
 *
 * The plan describes the full desired state. Positions are identified by a key: the id
 * of an existing position, or a negative number for one added in this plan. Shifts and
 * roster entries carry their id when they already exist; anything left out is removed.
 * Times are minutes from the start of the event (shifts) or occurrence (roster).
 */
final class StaffingPlan
{
    private const string CALLSIGN = '/^[A-Z0-9]+(_[A-Z0-9]+)+$/';

    private const string FREQUENCY = '/^1[1-3]\d\.\d{2,3}$/';

    /**
     * @param  array{
     *     positions: list<array{key: int, callsign: string, frequency?: string|null}>,
     *     shifts?: list<array{id?: int|null, position: int, start: int, end: int}>,
     *     roster?: list<array{id?: int|null, position: int, user_id: int, start: int, end: int}>,
     *     publish?: bool,
     * }  $plan
     *
     * @throws ValidationException when the plan breaks a staffing rule; nothing is saved
     */
    public function save(Event $event, EventOccurrence $occurrence, array $plan): void
    {
        if ($event->staffing_mode === StaffingMode::None) {
            $this->fail('Choose how controllers staff this event before adding positions.');
        }

        $positions = $this->positions($event, $plan['positions']);
        $callsigns = $positions->pluck('callsign', 'key');

        $shifts = $event->staffing_mode === StaffingMode::Shifts
            ? $this->shifts($event, $plan['shifts'] ?? [], $callsigns)
            : null;

        $editRoster = $event->staffing_mode === StaffingMode::OpenInterest && ! $occurrence->isRosterArchived();
        $roster = $editRoster ? $this->roster($occurrence, $plan['roster'] ?? [], $callsigns) : null;

        DB::transaction(function () use ($event, $occurrence, $positions, $shifts, $roster, $plan) {
            $ids = $this->savePositions($event, $positions);

            if ($shifts !== null) {
                $this->saveShifts($event, $shifts, $ids);
            }

            if ($roster !== null) {
                $this->saveRoster($occurrence, $roster, $ids);

                if ($plan['publish'] ?? false) {
                    $occurrence->publishRoster();
                }
            }
        });
    }

    /**
     * @param  list<array{key: int, callsign: string, frequency?: string|null}>  $planned
     * @return Collection<int, array{key: int, callsign: string, frequency: string|null}>
     */
    private function positions(Event $event, array $planned): Collection
    {
        $existing = $event->positions()->pluck('id');
        $seen = [];

        return collect($planned)->map(function (array $position) use ($existing, &$seen) {
            $callsign = strtoupper(trim($position['callsign']));
            $frequency = filled($position['frequency'] ?? null) ? trim($position['frequency']) : null;

            if ($position['key'] > 0 && ! $existing->contains($position['key'])) {
                $this->fail("{$callsign} was removed by someone else. Reload the page and try again.");
            }

            if (! preg_match(self::CALLSIGN, $callsign)) {
                $this->fail("“{$callsign}” isn't a callsign. Use one like EKCH_TWR.");
            }

            if ($frequency !== null && ! preg_match(self::FREQUENCY, $frequency)) {
                $this->fail("Use a frequency like 118.105 for {$callsign}.");
            }

            if (isset($seen[$callsign])) {
                $this->fail("{$callsign} is listed twice.");
            }

            $seen[$callsign] = true;

            return ['key' => $position['key'], 'callsign' => $callsign, 'frequency' => $frequency];
        });
    }

    /**
     * @param  list<array{id?: int|null, position: int, start: int, end: int}>  $planned
     * @param  Collection<int, string>  $callsigns
     * @return list<array{id: int|null, position: int, start: int, end: int, unbook: bool}>
     */
    private function shifts(Event $event, array $planned, Collection $callsigns): array
    {
        $existing = PositionShift::query()
            ->whereIn('event_position_id', $event->positions()->select('id'))
            ->withExists(['bookings as booked' => fn ($query) => $query->whereHas('occurrence', fn ($query) => $query->where('ends_at', '>', now()))])
            ->get()
            ->keyBy('id');
        $duration = $event->durationInMinutes();
        $shifts = [];

        foreach ($planned as $shift) {
            $id = $shift['id'] ?? null;
            $unbook = false;
            $callsign = $callsigns[$shift['position']] ?? $this->fail('A shift belongs to a position that is no longer in the plan.');
            $this->ensureWithin($shift['start'], $shift['end'], $duration, "A shift on {$callsign}");

            if ($id !== null) {
                $current = $existing[$id] ?? $this->fail("A shift on {$callsign} was removed by someone else. Reload the page and try again.");
                $moved = $current->event_position_id !== $shift['position']
                    || $current->starts_offset !== $shift['start']
                    || $current->ends_offset !== $shift['end'];

                // Moving a booked shift releases it: the controller is no longer booked.
                $unbook = $current->booked && $moved;
            }

            $shifts[] = ['id' => $id, 'position' => $shift['position'], 'start' => $shift['start'], 'end' => $shift['end'], 'unbook' => $unbook];
        }

        $this->ensureNoOverlap($shifts, 'position', fn (array $a) => "Two shifts on {$callsigns[$a['position']]} overlap.");

        return $shifts;
    }

    /**
     * @param  list<array{id?: int|null, position: int, user_id: int, start: int, end: int}>  $planned
     * @param  Collection<int, string>  $callsigns
     * @return list<array{id: int|null, position: int, user_id: int, start: int, end: int}>
     */
    private function roster(EventOccurrence $occurrence, array $planned, Collection $callsigns): array
    {
        $existing = $occurrence->rosterEntries()->pluck('id');
        $users = User::query()->whereKey(array_column($planned, 'user_id'))->get()->keyBy('id');
        $duration = (int) $occurrence->starts_at->diffInMinutes($occurrence->ends_at);
        $entries = [];

        foreach ($planned as $entry) {
            $id = $entry['id'] ?? null;
            $callsign = $callsigns[$entry['position']] ?? $this->fail('A roster entry belongs to a position that is no longer in the plan.');
            $user = $users[$entry['user_id']] ?? $this->fail("A controller rostered on {$callsign} no longer exists.");

            if (! $user->isEligibleController($occurrence->event)) {
                $this->fail("{$user->name} isn't a rated controller.");
            }

            if ($id !== null && ! $existing->contains($id)) {
                $this->fail("{$user->name}'s roster entry was removed by someone else. Reload the page and try again.");
            }

            $this->ensureWithin($entry['start'], $entry['end'], $duration, "{$user->name}'s roster entry on {$callsign}");

            $entries[] = ['id' => $id, 'position' => $entry['position'], 'user_id' => $user->id, 'start' => $entry['start'], 'end' => $entry['end']];
        }

        $this->ensureNoOverlap($entries, 'position', fn (array $a, array $b) => "{$users[$a['user_id']]->name} and {$users[$b['user_id']]->name} overlap on {$callsigns[$a['position']]}.");
        $this->ensureNoOverlap($entries, 'user_id', fn (array $a, array $b) => "{$users[$a['user_id']]->name} is rostered on {$callsigns[$a['position']]} and {$callsigns[$b['position']]} at the same time.");

        return $entries;
    }

    /**
     * @param  Collection<int, array{key: int, callsign: string, frequency: string|null}>  $positions
     * @return array<int, int> position ids by plan key
     */
    private function savePositions(Event $event, Collection $positions): array
    {
        // Removing a position takes its shifts, bookings, interest picks and roster entries with it.
        $event->positions()->whereNotIn('id', $positions->pluck('key')->filter(fn (int $key) => $key > 0))->delete();

        $ids = [];

        foreach ($positions->values() as $sort => $position) {
            $attributes = ['callsign' => $position['callsign'], 'frequency' => $position['frequency'], 'sort' => $sort];

            $ids[$position['key']] = $position['key'] > 0
                ? tap($event->positions()->findOrFail($position['key']))->update($attributes)->id
                : $event->positions()->create($attributes)->id;
        }

        return $ids;
    }

    /**
     * @param  list<array{id: int|null, position: int, start: int, end: int, unbook: bool}>  $shifts
     * @param  array<int, int>  $ids
     */
    private function saveShifts(Event $event, array $shifts, array $ids): void
    {
        // Removing a shift removes its bookings with it.
        PositionShift::query()
            ->whereIn('event_position_id', $event->positions()->select('id'))
            ->whereNotIn('id', array_filter(array_column($shifts, 'id')))
            ->delete();

        foreach ($shifts as $shift) {
            if ($shift['unbook']) {
                ShiftBooking::query()
                    ->where('position_shift_id', $shift['id'])
                    ->whereHas('occurrence', fn ($query) => $query->where('ends_at', '>', now()))
                    ->delete();
            }

            $attributes = ['event_position_id' => $ids[$shift['position']], 'starts_offset' => $shift['start'], 'ends_offset' => $shift['end']];

            $shift['id']
                ? PositionShift::query()->whereIn('event_position_id', $event->positions()->select('id'))->whereKey($shift['id'])->update($attributes)
                : PositionShift::create($attributes);
        }
    }

    /**
     * @param  list<array{id: int|null, position: int, user_id: int, start: int, end: int}>  $entries
     * @param  array<int, int>  $ids
     */
    private function saveRoster(EventOccurrence $occurrence, array $entries, array $ids): void
    {
        $occurrence->rosterEntries()->whereNotIn('id', array_filter(array_column($entries, 'id')))->delete();

        foreach ($entries as $entry) {
            $attributes = [
                'event_position_id' => $ids[$entry['position']],
                'user_id' => $entry['user_id'],
                'starts_at' => $occurrence->starts_at->addMinutes($entry['start']),
                'ends_at' => $occurrence->starts_at->addMinutes($entry['end']),
            ];

            $entry['id'] ? $occurrence->rosterEntries()->whereKey($entry['id'])->update($attributes) : $occurrence->rosterEntries()->create($attributes);
        }
    }

    private function ensureWithin(int $start, int $end, int $duration, string $what): void
    {
        if ($start < 0 || $end > $duration || $end <= $start) {
            $this->fail("{$what} falls outside the event.");
        }
    }

    /**
     * @param  list<array<string, mixed>>  $spans
     * @param  callable(array<string, mixed>, array<string, mixed>): string  $message
     */
    private function ensureNoOverlap(array $spans, string $groupBy, callable $message): void
    {
        foreach (collect($spans)->groupBy($groupBy) as $group) {
            $sorted = $group->sortBy('start')->values();

            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['start'] < $sorted[$i - 1]['end']) {
                    $this->fail($message($sorted[$i - 1], $sorted[$i]));
                }
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['plan' => $message]);
    }
}
