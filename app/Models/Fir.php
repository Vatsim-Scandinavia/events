<?php

namespace App\Models;

use App\Services\VaccIdentity;
use Database\Factories\FirFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A Flight Information Region the vACC covers, such as EKDK Copenhagen. It owns the events
 * it runs.
 */
class Fir extends Model
{
    /** @use HasFactory<FirFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['icao', 'name'];

    /**
     * The site lists the FIRs everywhere, so changing one refreshes that list. Roles held in a FIR
     * end with it. A deletion runs in a transaction, so the list is refreshed once that commits,
     * or a request in between could cache the FIR again.
     */
    protected static function booted(): void
    {
        static::deleting(fn (Fir $fir) => $fir->roleAssignments()->delete());
        static::saved(fn () => Cache::forget(VaccIdentity::FIRS_CACHE_KEY));
        static::deleted(fn () => DB::afterCommit(fn () => Cache::forget(VaccIdentity::FIRS_CACHE_KEY)));
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
