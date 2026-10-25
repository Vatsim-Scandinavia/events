<?php

use App\Integrations\Integrations;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Support\Facades\Schedule;

Schedule::command('events:extend-repeats')->daily()->withoutOverlapping();
Schedule::command('users:sync-vatsim')->daily()->withoutOverlapping();
// Forgets integration runs older than 90 days.
Schedule::command('model:prune')->daily();

app(Integrations::class)->schedule(app(Scheduler::class));
