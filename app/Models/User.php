<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

#[Fillable(['vatsim_cid', 'name', 'email', 'password', 'rating', 'subdivision', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'vatsim_cid' => 'integer',
            'rating' => ControllerRating::class,
        ];
    }

    /**
     * @return HasMany<ShiftBooking, $this>
     */
    public function shiftBookings(): HasMany
    {
        return $this->hasMany(ShiftBooking::class);
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * The roles the user holds, once per assignment. The pivot's fir_id is the FIR a role
     * applies in, or null when it applies across the hub.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_assignments')->withPivot('fir_id')->withTimestamps();
    }

    /**
     * Give the user a role across the hub, or only in the given FIR. Administrator always
     * applies across the hub. Holding it already changes nothing.
     */
    public function assignRole(Role|string $role, ?Fir $fir = null, AssignmentSource $source = AssignmentSource::Manual): RoleAssignment
    {
        $role = $role instanceof Role ? $role : Role::findByName($role);

        if ($fir !== null && $role->name === Roles::ADMINISTRATOR) {
            throw new InvalidArgumentException('Administrator always applies across the hub.');
        }

        $assignment = $this->roleAssignments()->firstOrCreate(['role_id' => $role->id, 'fir_id' => $fir?->id], ['source' => $source]);
        $this->unsetRelation('roles');

        return $assignment;
    }

    /**
     * Administrators may do everything, everywhere.
     */
    public function isAdministrator(): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->name === Roles::ADMINISTRATOR && $role->pivot->fir_id === null);
    }

    /**
     * Whether one of the user's roles grants the permission: across the hub or in the given
     * FIR, or in any FIR when none is given.
     */
    public function hasPermission(Permission $permission, ?Fir $fir = null): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles->contains(fn (Role $role) => ($fir === null || $role->pivot->fir_id === null || (int) $role->pivot->fir_id === $fir->id)
            && $role->permissions->contains('name', $permission->value));
    }

    /**
     * Whether the user holds the permission for an event: in the FIR that runs it, or across
     * the hub. Events from before FIRs existed have none, so only hub-wide roles count there.
     */
    public function hasPermissionFor(Permission $permission, Event $event): bool
    {
        if ($event->fir_id !== null) {
            return $this->hasPermission($permission, $event->fir);
        }

        return $this->permissionNames()->contains($permission->value);
    }

    /**
     * The permissions the user holds across the hub, by name, plus those held in the given
     * FIR. Without a FIR, roles held in a single FIR don't count.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(?Fir $fir = null): Collection
    {
        if ($this->isAdministrator()) {
            return collect(Roles::all());
        }

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->filter(fn (Role $role) => $role->pivot->fir_id === null || ($fir !== null && (int) $role->pivot->fir_id === $fir->id))
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * Whether the user may give and take away roles in the given FIR, or across the hub when
     * none is given. Managing users across the hub covers every FIR.
     */
    public function managesUsersIn(?Fir $fir): bool
    {
        return $this->permissionNames($fir)->contains(Permission::ManageUsers->value);
    }

    /**
     * Everyone who may staff the event: Administrators, and holders of a role that lets them
     * across the hub or in the FIR that runs it (the same rule as isEligibleController).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function controllers(Builder $query, Event $event): void
    {
        $query->whereHas('roles', fn (Builder $roles) => $roles
            ->where(fn (Builder $roles) => $roles
                ->where('name', Roles::ADMINISTRATOR)
                ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', Permission::BookPositions->value)))
            ->where(fn (Builder $roles) => $roles
                ->whereNull('role_assignments.fir_id')
                ->when($event->fir_id, fn (Builder $roles) => $roles->orWhere('role_assignments.fir_id', $event->fir_id))));
    }

    /**
     * Controllers (anyone holding the Controller role, or another role that lets them control)
     * may book shifts, show interest and be rostered on an event: with that role across the hub, or in the FIR that runs the event.
     */
    public function isEligibleController(Event $event): bool
    {
        return $this->hasPermissionFor(Permission::BookPositions, $event);
    }

    /**
     * The time zone event times are shown in: the one they picked, or UTC (zulu).
     */
    public function displayTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }
}
