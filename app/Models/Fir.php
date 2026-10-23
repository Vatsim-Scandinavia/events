<?php

namespace App\Models;

use App\Services\VaccIdentity;
use Database\Factories\FirFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

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
     * The site lists the FIRs everywhere, so changing one refreshes that list.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(VaccIdentity::FIRS_CACHE_KEY));
        static::deleted(fn () => Cache::forget(VaccIdentity::FIRS_CACHE_KEY));
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
