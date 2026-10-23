<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions. People hold roles through role assignments, each either
 * across the hub or in one FIR.
 */
#[Fillable(['name', 'permissions'])]
class Role extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'permissions' => '[]',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'permissions' => AsEnumCollection::of(Permission::class),
        ];
    }

    public static function findByName(string $name): self
    {
        return static::query()->where('name', $name)->firstOrFail();
    }

    public function grants(Permission $permission): bool
    {
        return $this->permissions->contains($permission);
    }

    /**
     * Everyone holding the role, once per assignment.
     *
     * @return BelongsToMany<User, $this>
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
