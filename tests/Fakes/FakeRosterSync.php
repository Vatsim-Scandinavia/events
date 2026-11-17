<?php

namespace Tests\Fakes;

use App\Auth\Controllers;
use App\Auth\ControllerSource;
use App\Integrations\Feature;
use App\Integrations\Syncable;
use App\Integrations\SyncContext;
use App\Integrations\SyncResult;
use App\Models\User;
use Closure;
use Illuminate\Console\Scheduling\Event;

/**
 * A roster sync that is also the Controller source: it gives Controller, hub-wide, to the CIDs
 * in $roster. Tests can replace what a sync does with $sync.
 */
class FakeRosterSync extends Feature implements ControllerSource, Syncable
{
    /** @var list<int> */
    public static array $roster = [];

    public static bool $decided = true;

    /** @var (Closure(SyncContext): SyncResult)|null */
    public static ?Closure $sync = null;

    public function key(): string
    {
        return 'roster';
    }

    public function name(): string
    {
        return 'Roster sync';
    }

    public function description(): string
    {
        return 'Makes everyone on the fake roster a Controller, Hub-wide.';
    }

    public function schedule(Event $event): void
    {
        $event->hourly();
    }

    public function sync(SyncContext $context): SyncResult
    {
        return (self::$sync ?? fn () => SyncResult::succeeded())($context);
    }

    public function controllerSourceName(): string
    {
        return 'Fake System roster sync';
    }

    public function hasDecided(): bool
    {
        return self::$decided;
    }

    public function syncController(User $user): void
    {
        if (in_array($user->vatsim_cid, self::$roster, true)) {
            $controllers = app(Controllers::class);
            $controllers->applySourceGrants($controllers->planSourceGrants(collect([['user' => $user, 'fir' => null]]), $user));
        }
    }
}
