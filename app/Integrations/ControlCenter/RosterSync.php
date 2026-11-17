<?php

namespace App\Integrations\ControlCenter;

use App\Auth\Controllers;
use App\Auth\ControllerSource;
use App\Integrations\Feature;
use App\Integrations\Integrations;
use App\Integrations\Syncable;
use App\Integrations\SyncContext;
use App\Integrations\SyncResult;
use App\Models\ControllerGrant;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\Fir;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Models\User;
use App\Staffing\StaffingNotes;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decides who is a Controller from Control Center's roster: in the FIR of every area someone is
 * active in, or holds a visiting endorsement for. Never Hub-wide.
 *
 * While it's the Controller Source, every source grant is its to give and take away, including
 * ones an earlier source gave. Hand grants are never touched. Until its first successful sync it
 * changes nothing, so switching it on never takes grants away before the roster is known.
 */
class RosterSync extends Feature implements ControllerSource, StaffingNotes, Syncable
{
    public function key(): string
    {
        return 'roster';
    }

    public function name(): string
    {
        return 'Roster sync';
    }

    public function description(): string
    {
        return 'Makes people Controllers in the FIRs where Control Center says they’re active, or have a visiting endorsement. Syncs every hour.';
    }

    public function schedule(Event $event): void
    {
        $event->hourly();
    }

    public function controllerSourceName(): string
    {
        return 'Control Center’s roster sync';
    }

    public function sync(SyncContext $context): SyncResult
    {
        $areas = AreaMap::parse($context->configuration->get('areas'));
        $members = (new ControlCenterApi($context->configuration))->members();

        $listed = collect($members)
            ->flatMap(fn (array $member) => [...array_keys($member['areas']), ...(new Member(['endorsements' => $member['endorsements']]))->controllingAreas()])
            ->unique();
        $missing = array_values(array_diff($areas->areas(), $listed->all()));
        $warnings = $this->warnings($areas, $listed);

        // A renamed or vanished area would otherwise stop everyone in its FIR being a Controller.
        if ($missing !== []) {
            return SyncResult::held(
                'Control Center no longer lists '.Str::of(collect($missing)->map(fn (string $area) => "“{$area}”")->join(', ', ' or '))
                .', mapped to a FIR. Check the areas, or approve to sync anyway.',
                $warnings,
            );
        }

        $now = now();
        Member::query()->delete();
        foreach (array_chunk($members, 500) as $chunk) {
            Member::query()->insert(array_map(fn (array $member) => [
                'cid' => $member['cid'],
                'rating' => $member['rating'],
                'active_areas' => json_encode(array_keys(array_filter($member['areas']))),
                'endorsements' => json_encode($member['endorsements']),
                'synced_at' => $now,
            ], $chunk));
        }

        $plan = $this->plan($areas);

        if (! $context->mayRemove($plan['remove']->count(), $plan['existing'])) {
            return SyncResult::held(
                "It would take away {$plan['remove']->count()} of {$plan['existing']} Controller Grants. Check Control Center, or approve to sync anyway.",
                $warnings,
            );
        }

        [$added, $removed] = app(Controllers::class)->applySourceGrants($plan);

        return SyncResult::succeeded(added: $added, removed: $removed, warnings: $warnings);
    }

    public function hasDecided(): bool
    {
        return Member::query()->exists();
    }

    public function syncController(User $user): void
    {
        // Not synced yet: the roster isn't known, so leave everyone as they are.
        if (! Member::query()->exists()) {
            return;
        }

        app(Controllers::class)->applySourceGrants($this->plan($this->areas(), $user));
    }

    /**
     * Warnings on the date's bookings and roster entries for controllers the roster doesn't
     * cover for their position (see Coverage).
     */
    public function staffingNotes(EventOccurrence $occurrence): array
    {
        if (! Member::query()->exists()) {
            return [];
        }

        $event = $occurrence->event;
        $assignments = collect([
            ...$occurrence->bookings()->with('shift.position', 'user')->get()
                ->map(fn (ShiftBooking $booking) => ["shift_booking:{$booking->id}", $booking->user, $booking->shift->position->callsign]),
            ...$occurrence->rosterEntries()->with('position', 'user')->get()
                ->map(fn (RosterEntry $entry) => ["roster_entry:{$entry->id}", $entry->user, $entry->position->callsign]),
        ]);
        $members = Member::query()->whereIn('cid', $assignments->map(fn (array $assignment) => $assignment[1]->vatsim_cid)->filter())->get()->keyBy('cid');
        $coverage = new Coverage($this->areas());
        $notes = [];

        foreach ($assignments as [$key, $user, $callsign]) {
            if ($problem = $coverage->problem($members->get($user->vatsim_cid), $callsign, $event->fir->icao)) {
                $notes[$key] = [$problem];
            }
        }

        return $notes;
    }

    public function positionNote(User $controller, EventPosition $position): ?string
    {
        if ($controller->vatsim_cid === null || ! Member::query()->exists()) {
            return null;
        }

        return (new Coverage($this->areas()))->problem(Member::query()->find($controller->vatsim_cid), $position->callsign, $position->event->fir->icao);
    }

    public function switchedOff(): void
    {
        Member::query()->delete();
    }

    private function areas(): AreaMap
    {
        return AreaMap::parse(app(Integrations::class)->configuration(app(ControlCenter::class))->get('areas'));
    }

    /**
     * The source grants to give and take away to match the stored roster, for everyone or one user.
     *
     * @return array{add: list<array{user: User, fir: Fir|null}>, remove: Collection<int, ControllerGrant>, existing: int}
     */
    private function plan(AreaMap $areas, ?User $only = null): array
    {
        $firs = Fir::query()->get()->keyBy('icao');
        $users = User::query()
            ->when($only, fn ($query) => $query->whereKey($only->id))
            ->whereIn('vatsim_cid', Member::query()->select('cid'))
            ->get()
            ->keyBy('vatsim_cid');

        $wanted = Member::query()->whereIn('cid', $users->keys())->get()->flatMap(fn (Member $member) => collect($member->controllingAreas())
            ->map(fn (string $area) => $firs->get((string) $areas->fir($area)))
            ->filter()
            ->unique('id')
            ->map(fn (Fir $fir) => ['user' => $users[$member->cid], 'fir' => $fir]));

        return app(Controllers::class)->planSourceGrants($wanted->values(), $only);
    }

    /**
     * Areas Control Center lists that nobody mapped, and mappings to FIRs the Hub doesn't have.
     *
     * @param  Collection<int, string>  $listed
     * @return list<string>
     */
    private function warnings(AreaMap $areas, Collection $listed): array
    {
        $firs = Fir::query()->pluck('icao');

        return [
            ...$listed->reject(fn (string $area) => $areas->fir($area) !== null)->sort()->map(fn (string $area) => "Control Center’s area “{$area}” isn’t mapped to a FIR, so it makes nobody a Controller.")->values()->all(),
            ...collect($areas->firs)->reject(fn (string $icao) => $firs->contains($icao))->map(fn (string $icao, string $area) => "“{$area}” is mapped to {$icao}, which isn’t one of the Hub’s FIRs.")->values()->all(),
        ];
    }
}
