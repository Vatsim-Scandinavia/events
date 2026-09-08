<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int|null $team_id
 * @property int $scope_id
 * @property string $source
 */
#[Fillable(['user_id', 'role_id', 'team_id', 'source'])]
class RoleGrant extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['team_id' => 'integer', 'scope_id' => 'integer'];
    }
}
