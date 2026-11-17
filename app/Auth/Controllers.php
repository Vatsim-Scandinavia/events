<?php

namespace App\Auth;

use App\Enums\ControllerGrantOrigin;
use App\Enums\ControllerRating;
use App\Integrations\Integrations;
use App\Models\ControllerGrant;
use App\Models\Event;
use App\Models\Fir;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Who is a Controller: an Account that may staff events (book shifts, show interest, be
 * rostered), Hub-wide or in a FIR. Being a Controller is not a role. An Account is one there when:
 *
 * - it is an Administrator, who has full rights everywhere;
 * - the Controller Rule applies to it: rated S1 or above in the vACC's subdivision, Hub-wide,
 *   worked out live from its Member Details whenever an admin switched the rule on; or
 * - it holds a Controller Grant there, given by the Controller Source or by hand.
 *
 * One decides at a time: the Controller Rule, or a Controller Source (an Integration such as
 * Control Center's roster sync). While a source is in charge the rule steps aside, and the source
 * owns every grant a source gave, including an earlier one's. Hand grants are never a source's
 * to touch, and switching a source off turns its grants into hand grants, so nobody loses access
 * without an admin deciding so.
 */
final class Controllers
{
    public function __construct(private VaccIdentity $identity, private Integrations $integrations, private Authorization $authorization) {}

    /**
     * The Integration feature deciding who is a Controller, or null when the Controller Rule does.
     */
    public function source(): ?ControllerSource
    {
        return $this->integrations->controllerSource();
    }

    /**
     * The Controller Rule decides: it's switched on, there's a subdivision to check against, and
     * no Controller Source is in charge, or the one switched on hasn't decided anything yet.
     */
    public function ruleIsInCharge(): bool
    {
        return ! $this->sourceIsInCharge() && $this->identity->controllerRuleIsOn() && $this->subdivision() !== null;
    }

    /**
     * A Controller Source is switched on and has decided who is a Controller.
     */
    public function sourceIsInCharge(): bool
    {
        return $this->source()?->hasDecided() ?? false;
    }

    /**
     * Whether the Controller Rule makes the user a Controller, Hub-wide.
     */
    public function ruleApplies(User $user): bool
    {
        return $this->ruleIsInCharge()
            && $user->subdivision !== null
            && strtoupper($user->subdivision) === $this->subdivision()
            && ($user->rating?->value ?? 0) >= ControllerRating::S1->value;
    }

    /**
     * Whether the user is a Controller there.
     */
    public function isController(User $user, Where $where): bool
    {
        return $this->authorization->isAdministrator($user)
            || $this->ruleApplies($user)
            || $user->controllerGrants->contains(fn (ControllerGrant $grant) => $where->covers($grant->fir_id));
    }

    /**
     * Whether the user may staff the event: a Controller Hub-wide, or in the FIR that runs it.
     */
    public function isControllerFor(User $user, Event $event): bool
    {
        return $this->isController($user, Where::in($event->fir));
    }

    /**
     * Everyone who may staff the event, by the same rules as isControllerFor.
     *
     * @return Builder<User>
     */
    public function controllersFor(Event $event): Builder
    {
        return User::query()->where(fn (Builder $query) => $query
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Roles::ADMINISTRATOR)->whereNull('role_assignments.fir_id'))
            ->orWhereHas('controllerGrants', fn (Builder $grants) => $grants->where(fn (Builder $grants) => $grants
                ->whereNull('fir_id')
                ->orWhere('fir_id', $event->fir_id)))
            ->when($this->ruleIsInCharge(), fn (Builder $query) => $query->orWhere(fn (Builder $query) => $this->whereRuleApplies($query))));
    }

    /**
     * How many Accounts are Controllers only by the Controller Rule, and so would no longer be
     * able to staff events if it were switched off.
     */
    public function onlyByRuleCount(): int
    {
        if (! $this->ruleIsInCharge()) {
            return 0;
        }

        return User::query()
            ->where(fn (Builder $query) => $this->whereRuleApplies($query))
            ->whereDoesntHave('controllerGrants')
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', Roles::ADMINISTRATOR))
            ->count();
    }

    /**
     * A user just signed in, so the source in charge may bring their grants up to date at once.
     */
    public function signedIn(User $user): void
    {
        $this->source()?->syncController($user);
    }

    /**
     * The source grants to give and take away so that the source's grants are exactly the wanted
     * ones, for everyone or only one user. A hand grant in a wanted place already covers it.
     *
     * @param  Collection<int, array{user: User, fir: Fir|null}>  $wanted
     * @return array{add: list<array{user: User, fir: Fir|null}>, remove: Collection<int, ControllerGrant>, existing: int}
     */
    public function planSourceGrants(Collection $wanted, ?User $only = null): array
    {
        $held = ControllerGrant::query()
            ->where('origin', ControllerGrantOrigin::Source)
            ->when($only, fn (Builder $query) => $query->where('user_id', $only->id))
            ->get();

        $key = fn (int $userId, ?int $firId) => $userId.':'.($firId ?? 0);
        $wantedKeys = $wanted->map(fn (array $grant) => $key($grant['user']->id, $grant['fir']?->id));
        $heldKeys = $held->map(fn (ControllerGrant $grant) => $key($grant->user_id, $grant->fir_id));

        return [
            'add' => $wanted->reject(fn (array $grant) => $heldKeys->contains($key($grant['user']->id, $grant['fir']?->id)))->values()->all(),
            'remove' => $held->reject(fn (ControllerGrant $grant) => $wantedKeys->contains($key($grant->user_id, $grant->fir_id)))->values(),
            'existing' => $held->count(),
        ];
    }

    /**
     * Carry out a plan from planSourceGrants.
     *
     * @param  array{add: list<array{user: User, fir: Fir|null}>, remove: Collection<int, ControllerGrant>, existing: int}  $plan
     * @return array{0: int, 1: int} How many grants were given and taken away.
     */
    public function applySourceGrants(array $plan): array
    {
        ControllerGrant::query()->whereKey($plan['remove']->modelKeys())->delete();

        $added = 0;
        foreach ($plan['add'] as $grant) {
            // A hand grant in the same place already covers it, and stays a hand grant.
            if ($grant['user']->controllerGrants()->firstOrCreate(['fir_id' => $grant['fir']?->id], ['origin' => ControllerGrantOrigin::Source])->wasRecentlyCreated) {
                $added++;
            }

            $grant['user']->unsetRelation('controllerGrants');
        }

        return [$added, $plan['remove']->count()];
    }

    /**
     * The Controller Source was switched off: what it gave becomes hand grants for admins to
     * keep or remove, and the Controller Rule takes over again.
     */
    public function sourceSwitchedOff(): void
    {
        ControllerGrant::query()->where('origin', ControllerGrantOrigin::Source)->update(['origin' => ControllerGrantOrigin::Hand]);
    }

    /**
     * Make the user a Controller by hand, in the FIR or Hub-wide, on the actor's behalf.
     *
     * @throws ValidationException when the actor doesn't manage users there, or the grant exists
     */
    public function grantByHand(User $actor, User $user, ?Fir $fir): ControllerGrant
    {
        $this->ensureManagesUsers($actor, $fir);

        $grant = $user->controllerGrants()->firstOrCreate(['fir_id' => $fir?->id], ['origin' => ControllerGrantOrigin::Hand]);
        $user->unsetRelation('controllerGrants');

        if (! $grant->wasRecentlyCreated) {
            throw ValidationException::withMessages(['controller' => "{$user->name} is already a Controller ".self::place($fir).'.']);
        }

        return $grant;
    }

    /**
     * Take away a hand grant on the actor's behalf. A source's grants are the source's to manage.
     *
     * @throws ValidationException when the actor doesn't manage users there, or a source gave it
     */
    public function revokeHandGrant(User $actor, ControllerGrant $grant): void
    {
        $grant->loadMissing('user', 'fir');
        $this->ensureManagesUsers($actor, $grant->fir);

        if ($grant->origin === ControllerGrantOrigin::Source) {
            $source = $this->source()?->controllerSourceName() ?? 'the Controller Source';

            throw ValidationException::withMessages(['controller' => "{$grant->user->name} is a Controller ".self::place($grant->fir)." from {$source}, so it can’t be removed here."]);
        }

        $grant->delete();
        $grant->user->unsetRelation('controllerGrants');
    }

    /**
     * Where the actor may give and take away hand grants: Hub-wide (null) and the FIRs (by id),
     * in that order.
     *
     * @param  Collection<int, Fir>  $firs
     * @return list<int|null>
     */
    public function grantableIn(User $actor, Collection $firs): array
    {
        return collect([null, ...$firs->all()])
            ->filter(fn (?Fir $fir) => $this->authorization->managesUsers($actor, Where::at($fir)))
            ->map(fn (?Fir $fir) => $fir?->id)
            ->values()
            ->all();
    }

    /**
     * "Hub-wide" or "in EKDK", for messages.
     */
    public static function place(?Fir $fir): string
    {
        return $fir !== null ? "in {$fir->icao}" : 'Hub-wide';
    }

    /**
     * @throws ValidationException
     */
    private function ensureManagesUsers(User $actor, ?Fir $fir): void
    {
        if (! $this->authorization->managesUsers($actor, Where::at($fir))) {
            throw ValidationException::withMessages([
                'controller' => $fir
                    ? 'You can only make someone a Controller in FIRs where you manage users.'
                    : 'You can only make someone a Controller Hub-wide if you manage users across the hub.',
            ]);
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    private function whereRuleApplies(Builder $query): void
    {
        $query->whereRaw('upper(subdivision) = ?', [$this->subdivision()])->where('rating', '>=', ControllerRating::S1->value);
    }

    private function subdivision(): ?string
    {
        $subdivision = $this->identity->effective()['subdivision'];

        return $subdivision !== null ? strtoupper($subdivision) : null;
    }
}
