<?php

namespace App\Models;

use App\Enums\ControllerGrantOrigin;
use Database\Factories\ControllerGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored decision that an Account is a Controller in one FIR, or Hub-wide when there's no FIR,
 * given by the Controller Source in charge or by hand. See App\Auth\Controllers.
 */
class ControllerGrant extends Model
{
    /** @use HasFactory<ControllerGrantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'fir_id', 'origin'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => ControllerGrantOrigin::class,
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
     * The FIR the grant applies in, or null when it applies Hub-wide.
     *
     * @return BelongsTo<Fir, $this>
     */
    public function fir(): BelongsTo
    {
        return $this->belongsTo(Fir::class);
    }
}
