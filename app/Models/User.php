<?php

namespace App\Models;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Enums\NameDisplay;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use InvalidArgumentException;

#[Fillable(['vatsim_cid', 'name', 'first_name', 'name_display', 'email', 'rating', 'subdivision', 'timezone'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * First name is how a name is shown until someone picks otherwise, as the column defaults to,
     * so a user not saved yet is shown the same way.
     *
     * @var array<string, string>
     */
    protected $attributes = ['name_display' => 'first_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vatsim_cid' => 'integer',
            'rating' => ControllerRating::class,
            'name_display' => NameDisplay::class,
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
     * The time zone event times are shown in: the one they picked, or UTC (zulu).
     */
    public function displayTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    /**
     * The name everyone else sees, as the user chose: their full name, their first name, or
     * just their CID. Until VATSIM has shared a first name, it is the first word of the name.
     * Passing a choice shows the name as it would be with that one instead.
     */
    public function displayName(?NameDisplay $display = null): string
    {
        return match ($display ?? $this->name_display) {
            NameDisplay::FullName => $this->name,
            NameDisplay::FirstName => $this->first_name ?: strtok($this->name, ' '),
            NameDisplay::CidOnly => (string) $this->vatsim_cid,
        };
    }

    /**
     * Whether the CID goes beside the name, which it does unless the name shown is the CID.
     */
    public function showsCidBesideName(?NameDisplay $display = null): bool
    {
        return ($display ?? $this->name_display) !== NameDisplay::CidOnly;
    }

    /**
     * The display name followed by the CID in brackets, or the CID alone when that is the name.
     */
    public function displayNameWithCid(?NameDisplay $display = null): string
    {
        return $this->showsCidBesideName($display) ? "{$this->displayName($display)} ({$this->vatsim_cid})" : $this->displayName($display);
    }
}
