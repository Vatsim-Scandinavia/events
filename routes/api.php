<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\StaffingApiController;

/**
 * v2 API Routes
 */

Route::prefix('v2')->name('apiv2.')->group(function () {
    // Events
    Route::get('events', [EventApiController::class, 'index'])->name('events.index');
    Route::get('events/{id}', [EventApiController::class, 'show'])->name('events.show');
    Route::delete('events/{id}', [EventApiController::class, 'destroy'])->name('events.destroy');

    Route::get('staffings', [StaffingApiController::class, 'index'])->name('staffings.index');
    Route::get('staffings/{id}', [StaffingApiController::class, 'show'])->name('staffings.show');
});

/**
 * LEGACY ROUTES - TO BE REMOVED LATER
 */

Route::name('apilegacy.')->group(function () {
    // Legacy Events routes
    Route::get('events', [\App\Http\Controllers\Api\ApiController::class, 'events'])->name('events');
    Route::get('events/{id}', [\App\Http\Controllers\Api\ApiController::class, 'event'])->name('event');
    Route::get('events/{id}/staffing', [\App\Http\Controllers\Api\ApiController::class, 'staffing'])->name('event.staffing');

    // Legacy Staffing routes
    Route::get('staffings/{id}', [\App\Http\Controllers\Api\ApiController::class, 'getStaffing'])->name('staffing.show');
    Route::patch('staffings/{id}/update', [\App\Http\Controllers\Api\ApiController::class, 'updateStaffing'])->name('staffing.update');
    Route::post('staffings/{id}/reset', [\App\Http\Controllers\Api\ApiController::class, 'resetStaffing'])->name('staffing.reset');
    Route::post('staffing', [\App\Http\Controllers\Api\ApiController::class, 'book'])->name('staffing.store');
    Route::post('staffings/book', [\App\Http\Controllers\Api\ApiController::class, 'book'])->name('staffing.book'); // Alternative route
    Route::delete('staffing', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.destroy');
    Route::delete('staffings/unbook', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.unbook'); // Alternative route
    Route::post('staffings/unbook', [\App\Http\Controllers\Api\ApiController::class, 'unbook'])->name('staffing.unbook-post'); // Bot uses POST
    Route::post('staffing/setup', [\App\Http\Controllers\Api\ApiController::class, 'setup'])->name('staffing.setup');
    Route::get('staffings', function (\Illuminate\Http\Request $request) {
        $apiController = app(\App\Http\Controllers\Api\ApiController::class);
        if ($request->has('message_id')) {
            return $apiController->getStaffingByMessageId($request);
        }
        return $apiController->getAllStaffings();
    })->name('staffing.index');
});
