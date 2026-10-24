<?php

namespace Tests\Fakes;

use App\Auth\ControllerSource;
use App\Auth\Roles;
use App\Enums\AssignmentSource;
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
        return 'Gives Controller to everyone on the fake roster.';
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

    public function syncController(User $user): void
    {
        if (in_array($user->vatsim_cid, self::$roster, true)) {
            $user->assignRole(Roles::CONTROLLER, null, AssignmentSource::Automatic);
        }
    }
}
