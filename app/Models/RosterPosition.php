<?php

namespace App\Models;

use Database\Factories\RosterPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $roster_id
 * @property string $callsign
 */
#[Fillable(['roster_id', 'callsign'])]
class RosterPosition extends Model
{
    /** @use HasFactory<RosterPositionFactory> */
    use HasFactory;

    /** @return BelongsTo<EventRoster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(EventRoster::class, 'roster_id');
    }
}
