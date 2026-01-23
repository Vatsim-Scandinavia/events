<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventApiController;

/**
 * v2 API Routes
 */

Route::prefix('v2')->name('apiv2.')->group(function () {
    // Events
    Route::get('events', [EventApiController::class, 'index'])->name('events.index');
    Route::get('events/{id}', [EventApiController::class, 'show'])->name('events.show');
    Route::delete('events/{id}', [EventApiController::class, 'destroy'])->name('events.destroy');
});

/**
 * LEGACY ROUTES - TO BE REMOVED LATER
 */

Route::name('apilegacy.')->group(function () {
    // Legacy Events routes
    Route::get('events', [\App\Http\Controllers\Api\ApiController::class, 'events'])->name('events');
    Route::get('events/{id}', [\App\Http\Controllers\Api\ApiController::class, 'event'])->name('event');
    Route::get('events/{id}/staffing', [\App\Http\Controllers\Api\ApiController::class, 'staffing'])->name('event.staffing');
    
});