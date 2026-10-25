<?php

use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\OccurrenceController;
use App\Http\Controllers\Api\V1\RosterController;
use Illuminate\Support\Facades\Route;

// The public read API. v1 only ever grows; see docs/adr/0003-versioned-public-read-api.md.
Route::prefix('v1')->name('api.v1.')->middleware(['throttle:api', 'cache.headers:public;max_age=60'])->group(function () {
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event')->name('events.show');
    Route::get('/occurrences', [OccurrenceController::class, 'index'])->name('occurrences.index');
    Route::get('/occurrences/{occurrence}', [OccurrenceController::class, 'show'])->whereNumber('occurrence')->name('occurrences.show');
    Route::get('/occurrences/{occurrence}/roster', [RosterController::class, 'index'])->whereNumber('occurrence')->name('occurrences.roster.index');
});
