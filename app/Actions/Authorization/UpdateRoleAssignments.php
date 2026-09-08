<?php

namespace App\Actions\Authorization;

use App\Models\RoleGrant;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class UpdateRoleAssignments
{
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
        });
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

    private function update(User $user, Closure $change): void
    {
        DB::transaction(function () use ($user, $change): void {
            User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $change();

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
        });

        $user->unsetRelation('roles')->unsetRelation('permissions')->unsetRelation('roleGrants')->unsetRelation('teams');
    }
}
