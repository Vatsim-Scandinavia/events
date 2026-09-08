<?php

namespace App\Models;

use App\PermissionName;
use App\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $cid
 * @property string $name_full
 * @property string $email
 * @property int $controller_rating
 * @property string|null $division
 * @property string|null $subdivision
 * @property string|null $oauth_provider
 * @property string|null $oauth_id
 * @property string|null $oauth_access_token
 * @property string|null $oauth_refresh_token
 * @property Carbon|null $oauth_expires_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cid', 'name_full', 'email', 'controller_rating', 'division', 'subdivision', 'oauth_provider', 'oauth_id', 'oauth_access_token', 'oauth_refresh_token', 'oauth_expires_at'])]
#[Hidden(['oauth_access_token', 'oauth_refresh_token', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $primaryKey = 'cid';

    public $incrementing = false;

    /** @return HasMany<RoleGrant, $this> */
    public function roleGrants(): HasMany
    {
        return $this->hasMany(RoleGrant::class, 'user_id', 'cid');
    }

    /**
     * All effective assignments, independent of Spatie's current team context.
     *
     * @return MorphToMany<Role, $this>
     */
    public function assignedRoles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles')
            ->withPivot('team_id');
    }

    public function isAdministrator(): bool
    {
        return $this->assignedRoles()->wherePivot('team_id', 0)
            ->where('name', RoleName::Administrator->value)->where('guard_name', 'web')->exists();
    }

    /** @return Collection<int, string> */
    public function effectiveRoleNames(): Collection
    {
        $roles = $this->relationLoaded('assignedRoles')
            ? $this->assignedRoles
            : $this->assignedRoles()->get();
        $names = $roles->where('guard_name', 'web')->pluck('name')->unique()->sort()->values();

        return $names->isEmpty() ? collect([RoleName::Pilot->value]) : $names;
    }

    public function hasPermissionInTeam(PermissionName $permission, Team $team): bool
    {
        if (! $team->exists || ! Team::whereKey($team->getKey())->exists()) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $scopedUser = clone $this;
        $scopedUser->unsetRelation('roles')->unsetRelation('permissions');

        try {
            $registrar->setPermissionsTeamId($team->getKey());

            return $scopedUser->checkPermissionTo($permission, 'web');
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'controller_rating' => 'integer',
            'oauth_access_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at' => 'datetime',
        ];
    }
}
