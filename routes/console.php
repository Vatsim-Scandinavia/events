<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('events:extend-repeats')->daily()->withoutOverlapping();
Schedule::command('users:sync-vatsim')->daily()->withoutOverlapping();
