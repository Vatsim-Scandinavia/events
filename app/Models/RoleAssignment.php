<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone holding a role, either across the hub (no FIR) or in one FIR.
 */
class RoleAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'role_id', 'fir_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The FIR the role applies in, or null when it applies across the hub.
     *
     * @return BelongsTo<Fir, $this>
     */
    public function fir(): BelongsTo
    {
        return $this->belongsTo(Fir::class);
    }
}
