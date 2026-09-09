<?php

namespace App\Actions\Authorization;

use App\Actions\RecordAudit;
use App\Models\RoleGrant;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class UpdateRoleAssignments
{
    public function __construct(private RecordAudit $audit) {}

    public function grant(User $user, RoleName $role, ?Team $team = null): void
    {
        $this->validateScope($role, $team);

        $this->update($user, fn () => $this->persistGrant($user, $role, $team, 'manual'));
    }

    public function revoke(User $user, RoleName $role, ?Team $team = null): void
    {
        $this->validateScope($role, $team);

        $this->update($user, function () use ($user, $role, $team): void {
            $user->roleGrants()->where('source', 'manual')
                ->where('role_id', $this->role($role)->getKey())
                ->where('team_id', $team?->getKey())->delete();
        });
    }

    /**
     * Apply one trusted source's complete snapshot for this user.
     * Call only after a successful, complete fetch from that source.
     *
     * @param  list<array{role: RoleName, team: Team|null}>  $assignments
     */
    public function syncExternal(User $user, string $source, array $assignments): void
    {
        $sources = config('authorization.external_role_sources', []);

        if ($source === 'manual' || ! array_key_exists($source, $sources) || mb_strlen($source) > 64) {
            throw new InvalidArgumentException('The external role source is not configured.');
        }

        foreach ($assignments as $assignment) {
            $this->validateScope($assignment['role'], $assignment['team']);

            if (! in_array($assignment['role']->value, $sources[$source], true)) {
                throw new InvalidArgumentException('The source is not allowed to grant this role.');
            }
        }

        $this->update($user, function () use ($user, $source, $assignments): void {
            $user->roleGrants()->where('source', $source)->delete();

            foreach ($assignments as $assignment) {
                $this->persistGrant($user, $assignment['role'], $assignment['team'], $source);
            }
        }, $source);
    }

    private function validateScope(RoleName $role, ?Team $team): void
    {
        if ($role === RoleName::Pilot) {
            throw new InvalidArgumentException('Pilot is the automatic fallback and cannot be assigned.');
        }

        if ($role->isGlobal() !== ($team === null)) {
            throw new InvalidArgumentException('This role requires a different FIR scope.');
        }

        if ($team !== null && (! $team->exists || ! Team::whereKey($team->getKey())->exists())) {
            throw new InvalidArgumentException('The FIR must exist before assigning a role.');
        }
    }

    private function persistGrant(User $user, RoleName $role, ?Team $team, string $source): void
    {
        $user->roleGrants()->firstOrCreate([
            'role_id' => $this->role($role)->getKey(),
            'team_id' => $team?->getKey(),
            'source' => $source,
        ]);
    }

    private function role(RoleName $role): Role
    {
        return Role::where('name', $role->value)->where('guard_name', 'web')->whereNull('team_id')->firstOrFail();
    }

    private function update(User $user, Closure $change, string $source = 'manual'): void
    {
        DB::transaction(function () use ($user, $change, $source): void {
            $administratorRole = $this->lockAdministratorRole();
            $user = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $wasAdministrator = $user->roleGrants()->where('role_id', $administratorRole->getKey())
                ->whereNull('team_id')->lockForUpdate()->first(['id']) !== null;
            $before = $this->snapshot($user);
            $change();

            if ($wasAdministrator && RoleGrant::where('role_id', $administratorRole->getKey())
                ->whereNull('team_id')->lockForUpdate()->first(['id']) === null) {
                throw ValidationException::withMessages(['role' => 'At least one Administrator must remain.']);
            }

            $assignments = $user->roleGrants()->select('role_id', 'scope_id')->distinct()->get()
                ->map(fn (RoleGrant $grant): array => [
                    'role_id' => $grant->role_id,
                    'team_id' => $grant->scope_id,
                    'model_id' => $user->cid,
                    'model_type' => $user->getMorphClass(),
                ])->all();

            DB::table('model_has_roles')->where('model_id', $user->getKey())
                ->where('model_type', $user->getMorphClass())->delete();

            if ($assignments !== []) {
                DB::table('model_has_roles')->insert($assignments);
            }

            $this->audit->handle($user, 'roles_updated', ['roles' => $before], ['roles' => $this->snapshot($user)], $source);
        });

        $user->unsetRelation('roles')->unsetRelation('assignedRoles')->unsetRelation('permissions')->unsetRelation('roleGrants')->unsetRelation('teams');
    }

    /**
     * Serialize role changes before reading grants, including changes to different users.
     * The unchanged write also acquires SQLite's write lock, where FOR UPDATE is ignored.
     */
    private function lockAdministratorRole(): Role
    {
        $query = Role::where('name', RoleName::Administrator->value)->where('guard_name', 'web')->whereNull('team_id');
        $query->toBase()->update(['name' => RoleName::Administrator->value]);

        return $query->firstOrFail();
    }

    /** @return list<array{role: string, fir_id: int|null, fir: string|null, source: string}> */
    private function snapshot(User $user): array
    {
        return array_values($user->roleGrants()->join('roles', 'role_grants.role_id', '=', 'roles.id')
            ->leftJoin('teams', 'role_grants.team_id', '=', 'teams.id')
            ->orderBy('roles.name')->orderBy('role_grants.scope_id')->orderBy('role_grants.source')
            ->get(['roles.name as role_name', 'role_grants.team_id', 'teams.code as fir_code', 'role_grants.source'])
            ->map(fn (RoleGrant $grant): array => [
                'role' => (string) $grant->getAttribute('role_name'),
                'fir_id' => $grant->team_id,
                'fir' => $grant->team_id === null ? null : (string) $grant->getAttribute('fir_code'),
                'source' => $grant->source,
            ])->all());
    }
}
