<?php

namespace App\Models;

use Database\Factories\RosterInterestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $roster_id
 * @property int $user_cid
 * @property list<int> $position_ids
 * @property list<array{starts_at: string, ends_at: string}> $availability
 * @property User $user
 */
#[Fillable(['roster_id', 'user_cid', 'position_ids', 'availability'])]
class RosterInterest extends Model
{
    /** @use HasFactory<RosterInterestFactory> */
    use HasFactory;

    /** @return BelongsTo<EventRoster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(EventRoster::class, 'roster_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_cid', 'cid');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_cid' => 'integer', 'position_ids' => 'array', 'availability' => 'array'];
    }
}
