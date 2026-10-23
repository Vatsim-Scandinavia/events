<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A named set of permissions. People hold roles through role assignments, each either
 * across the hub or in one FIR.
 */
class Role extends SpatieRole
{
    /**
     * Everyone holding the role, once per assignment.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_assignments')->withPivot('fir_id')->withTimestamps();
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
