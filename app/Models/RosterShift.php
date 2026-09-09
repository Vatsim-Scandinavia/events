<?php

namespace App\Models;

use Database\Factories\RosterShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $roster_id
 * @property string $name
 * @property EventRoster $roster
 * @property Collection<int, RosterSlot> $slots
 */
#[Fillable(['roster_id', 'name'])]
class RosterShift extends Model
{
    /** @use HasFactory<RosterShiftFactory> */
    use HasFactory;

    /** @return BelongsTo<EventRoster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(EventRoster::class, 'roster_id');
    }

    /** @return HasMany<RosterSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(RosterSlot::class, 'shift_id')->orderBy('id');
    }
}
