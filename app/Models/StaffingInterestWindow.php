<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of time a controller is available during an occurrence.
 */
class StaffingInterestWindow extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['starts_at', 'ends_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<StaffingInterest, $this>
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(StaffingInterest::class, 'staffing_interest_id');
    }
}
