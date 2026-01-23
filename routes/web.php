<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ControlCenterController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StaffingController;
use App\Http\Controllers\StaffingPositionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');

// Authentication routes
Route::get('/auth/vatsim', [AuthController::class, 'redirectToProvider'])->name('auth.vatsim');
Route::get('/auth/callback', [AuthController::class, 'handleProviderCallback'])->name('auth.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Development login routes (only in local environment)
Route::get('/dev/login', [AuthController::class, 'showDevLogin'])->name('dev.login');

// Spatie Login Link routes are automatically registered by the package
// They handle the actual login when clicking a login link

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Calendars (except index and show which are public)
    Route::resource('calendars', CalendarController::class)->except(['index', 'show']);

    // Events (except index and show which are public)
    Route::resource('events', EventController::class)->except(['index', 'show']);
    Route::post('events/{event}/banner', [EventController::class, 'uploadBanner'])->name('events.banner');
    Route::get('events/{event}/occurrences', [EventController::class, 'manageOccurrences'])->name('events.occurrences');
    Route::post('events/{event}/cancel-occurrence', [EventController::class, 'cancelOccurrence'])->name('events.cancel-occurrence');
    Route::post('events/{event}/uncancel-occurrence', [EventController::class, 'uncancelOccurrence'])->name('events.uncancel-occurrence');

    // Staffings
    Route::resource('events.staffings', StaffingController::class)->shallow()->except(['show']);
    Route::post('staffings/{staffing}/reorder', [StaffingController::class, 'reorder'])->name('staffings.reorder');
    Route::post('events/{event}/staffings/reset', [StaffingController::class, 'reset'])->name('staffings.reset');

    // Staffing Positions
    Route::resource('staffings.positions', StaffingPositionController::class)->shallow()->only(['store', 'update', 'destroy']);
    Route::post('positions/reorder', [StaffingPositionController::class, 'reorder'])->name('positions.reorder');

    // Unbooking only (booking happens through Discord bot)
    Route::delete('positions/{position}/book', [StaffingPositionController::class, 'unbook'])->name('positions.unbook');

    // Control Center Integration
    Route::get('api/positions', [ControlCenterController::class, 'getPositions'])->name('api.positions');
    Route::post('api/positions/cache/clear', [ControlCenterController::class, 'clearCache'])->name('api.positions.clear-cache');

    // Discord Integration - get available channels for event setup
    Route::get('api/discord/channels', function () {
        $discordService = app(\App\Services\DiscordChannelService::class);
        return response()->json($discordService->getAvailableChannels());
    })->name('api.discord.channels');
});

// Public calendar/event viewing (guest-friendly)
Route::get('/calendars', [CalendarController::class, 'index'])->name('calendars.index');
Route::get('/calendars/{calendar}', [CalendarController::class, 'show'])->name('calendars.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
