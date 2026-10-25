<?php

namespace App\Integrations\ControlCenter;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone Control Center listed at its last roster sync, by CID, whether or not they have ever
 * signed in to the Hub: the areas they're active in and their endorsements. Read-only in the
 * Hub; each successful sync replaces them all.
 *
 * @property int $cid
 * @property ?string $rating
 * @property list<string> $active_areas
 * @property array<string, mixed> $endorsements
 */
class Member extends Model
{
    protected $table = 'control_center_members';

    protected $primaryKey = 'cid';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['cid', 'rating', 'active_areas', 'endorsements', 'synced_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_areas' => 'array',
            'endorsements' => 'array',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /**
     * The areas that give them the Controller role: where they're active, and where a visiting
     * endorsement lets them control. Solo, facility and examiner endorsements give nothing.
     *
     * @return list<string>
     */
    public function controllingAreas(): array
    {
        $visiting = collect($this->endorsements['visiting'] ?? [])
            ->flatMap(fn (mixed $endorsement) => is_array($endorsement) && is_array($endorsement['areas'] ?? null) ? $endorsement['areas'] : [])
            ->filter(fn (mixed $area) => is_string($area))
            ->map(fn (string $area) => mb_strtolower(trim($area)));

        return collect($this->active_areas)->merge($visiting)->unique()->values()->all();
    }
}
