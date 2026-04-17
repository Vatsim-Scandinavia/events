<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StaffingController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('events', [EventController::class, 'index'])->name('events.index');

// Authentication routes
Route::get('/auth/vatsim', [LoginController::class, 'redirectToProvider'])->name('login');
Route::get('/auth/callback', [LoginController::class, 'handleProviderCallback'])->name('auth.callback');
Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout')->middleware('auth');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Calendars
    Route::resource('calendars', CalendarController::class);

    // Events (except index and show which are public)
    Route::resource('events', EventController::class)->except(['index', 'show']);
    Route::get('events/{event}/occurrences', [EventController::class, 'manageOccurrences'])->name('events.occurrences');
    Route::post('events/{event}/cancel-occurrence', [EventController::class, 'cancelOccurrence'])->name('events.cancel-occurrence');
    Route::post('events/{event}/uncancel-occurrence', [EventController::class, 'uncancelOccurrence'])->name('events.uncancel-occurrence');

    // Staffing management
    Route::get('events/{event}/staffing', [StaffingController::class, 'edit'])->name('events.staffing.edit');
    Route::put('events/{event}/staffing', [StaffingController::class, 'update'])->name('events.staffing.update');

    // Discord Integration - get available channels for event setup
    Route::get('internal/discord/channels', [\App\Http\Controllers\Api\DiscordController::class, 'getChannels'])->name('api.discord.channels');

    // Control Center Integration - fetch known ATC positions (cached)
    Route::get('internal/positions', [\App\Http\Controllers\Api\ControlCenterController::class, 'positions'])->name('api.positions');
});

// Must be defined after the resource routes so events/create is not swallowed by the {event} wildcard
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

// Public API routes (backward compatible with old events system)
Route::prefix('api')->name('api.')->middleware('api.key')->group(function () {
    Route::get('events', [\App\Http\Controllers\Api\ApiController::class, 'events'])->name('events');
    Route::get('events/{id}', [\App\Http\Controllers\Api\ApiController::class, 'event'])->name('event');
    Route::get('events/{id}/staffing', [\App\Http\Controllers\Api\ApiController::class, 'staffing'])->name('event.staffing');

    // Staffing routes matching old format
    // GET /api/staffings - list all staffings
    // GET /api/staffings?message_id=xxx - get by message_id
    Route::get('staffings', function (\Illuminate\Http\Request $request) {
        $apiController = app(\App\Http\Controllers\Api\ApiController::class);
        if ($request->has('message_id')) {
            return $apiController->getStaffingByMessageId($request);
        }
        return $apiController->getAllStaffings();
    })->name('staffing.index');

    Route::get('staffings/{id}', [\App\Http\Controllers\Api\ApiController::class, 'getStaffing'])->name('staffing.show');
    Route::patch('staffings/{id}/update', [\App\Http\Controllers\Api\ApiController::class, 'updateStaffing'])->name('staffing.update');
    Route::post('staffings/{id}/reset', [\App\Http\Controllers\Api\ApiController::class, 'resetStaffing'])->name('staffing.reset');
    Route::post('staffing', [\App\Http\Controllers\Api\ApiController::class, 'book'])->name('staffing.store');
    Route::post('staffings/book', [\App\Http\Controllers\Api\ApiController::class, 'book'])->name('staffing.book'); // Alternative route
    Route::delete('staffing', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.destroy');
    // Bot uses POST for unbook; DELETE /api/staffing kept for backward compatibility
    Route::post('staffings/unbook', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.unbook-post');
    Route::post('staffing/setup', [\App\Http\Controllers\Api\ApiController::class, 'setup'])->name('staffing.setup');
});
