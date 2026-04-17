<?php

use App\Jobs\GenerateEventOccurrences;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new GenerateEventOccurrences)->daily();
