<?php

use App\Http\Controllers\Auth\LoginController;
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
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/vatsim', [LoginController::class, 'redirectToProvider'])->name('vatsim');
    Route::get('/callback', [LoginController::class, 'handleProviderCallback'])->name('callback');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
});

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

// Public API routes (backward compatible with old events system)
Route::prefix('api')->name('api.')->group(function () {
    Route::get('events', [\App\Http\Controllers\Api\ApiController::class, 'events'])->name('events');
    Route::get('events/{id}', [\App\Http\Controllers\Api\ApiController::class, 'event'])->name('event');
    Route::get('events/{id}/staffing', [\App\Http\Controllers\Api\ApiController::class, 'staffing'])->name('event.staffing');
    
    // Staffing routes matching old format
    // GET /api/staffings - list all staffings
    // GET /api/staffings?message_id=xxx - get by message_id
    Route::get('staffings', function(\Illuminate\Http\Request $request) {
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
    Route::delete('staffings/unbook', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.unbook'); // Alternative route
    Route::post('staffings/unbook', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.unbook-post'); // Bot uses POST
    Route::post('staffing/setup', [\App\Http\Controllers\Api\ApiController::class, 'setup'])->name('staffing.setup');
});

// Public calendar/event viewing (guest-friendly)
Route::get('/calendars', [CalendarController::class, 'index'])->name('calendars.index');
Route::get('/calendars/{calendar}', [CalendarController::class, 'show'])->name('calendars.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
