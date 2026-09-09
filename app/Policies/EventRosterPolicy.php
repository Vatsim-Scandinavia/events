<?php

namespace App\Policies;

use App\Actions\EventSchedule;
use App\Models\Event;
use App\Models\EventRoster;
use App\Models\User;
use Carbon\CarbonImmutable;

class EventRosterPolicy
{
    public function __construct(private EventSchedule $schedule) {}

    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', Event::class) || $user->controllerTeams()->exists();
    }

    public function view(User $user, EventRoster $roster): bool
    {
        return $user->can('view', $roster->event)
            || ($roster->opened_at !== null && $this->isEligibleController($user));
    }

    public function update(User $user, EventRoster $roster): bool
    {
        return $user->can('update', $roster->event);
    }

    public function participate(User $user, EventRoster $roster): bool
    {
        if (! $roster->is_open || ! $this->isEligibleController($user)) {
            return false;
        }

        $occurrence = $this->schedule->occurrence($roster->event, $roster->occurrence_date);

        return $occurrence !== null && $occurrence['status'] === 'scheduled'
            && CarbonImmutable::parse($occurrence['ends_at'])->isFuture();
    }

    private function isEligibleController(User $user): bool
    {
        return $user->controllerTeams()->exists();
    }
}
