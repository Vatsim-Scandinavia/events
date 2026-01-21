<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public endpoints (optional API key)
    Route::middleware('api.key.optional')->group(function () {
        Route::get('events', [ApiController::class, 'events'])->name('events');
        Route::get('events/{id}', [ApiController::class, 'event'])->name('event');
    });

    // Protected read endpoints (required API key)
    Route::middleware('api.key')->group(function () {
        // Event staffing
        Route::get('events/{id}/staffing', [ApiController::class, 'staffing'])->name('event.staffing');
        
        // Staffing endpoints
        Route::get('staffings', [ApiController::class, 'getAllStaffings'])->name('staffings.index');
        Route::get('staffings/message', [ApiController::class, 'getStaffingByMessageId'])->name('staffings.by-message');
        Route::get('staffings/{id}', [ApiController::class, 'getStaffing'])->name('staffings.show');
    });

    // Write-protected endpoints (required write API key)
    Route::middleware('api.key:write')->group(function () {
        // Booking operations
        Route::post('staffing', [ApiController::class, 'book'])->name('staffing.book');
        Route::delete('staffing', [ApiController::class, 'unbook'])->name('staffing.unbook');
        
        // Staffing management
        Route::post('staffing/setup', [ApiController::class, 'setup'])->name('staffing.setup');
        Route::patch('staffings/{id}/update', [ApiController::class, 'updateStaffing'])->name('staffings.update');
        Route::post('staffings/{id}/reset', [ApiController::class, 'resetStaffing'])->name('staffings.reset');
    });
});