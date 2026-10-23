<?php

namespace App\Models;

use App\Enums\AssignmentSource;
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
    protected $fillable = ['user_id', 'role_id', 'fir_id', 'source'];

    /**
     * Given by an admin unless the hub handed it out (the automatic Controller role).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
    ];

    /**
     * fir_key mirrors fir_id, with 0 for across the hub, so the database can refuse a second copy
     * of the same role in the same place (see the make_role_assignments_unique migration).
     */
    protected static function booted(): void
    {
        static::saving(function (RoleAssignment $assignment) {
            $assignment->fir_key = $assignment->fir_id ?? 0;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AssignmentSource::class,
        ];
    }

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
