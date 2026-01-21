<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\StaffingController;
use App\Http\Controllers\Api\BookingController;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public endpoints (optional API key)
    Route::middleware('api.key.optional')->group(function () {
        Route::get('events', [EventController::class, 'index'])->name('events');
        Route::get('events/{id}', [EventController::class, 'show'])->name('event');
    });

    // Protected read endpoints (required API key)
    Route::middleware('api.key')->group(function () {
        // Event staffing
        Route::get('events/{id}/staffing', [StaffingController::class, 'getEventStaffing'])->name('event.staffing');
        
        // Staffing endpoints
        Route::get('staffings', [StaffingController::class, 'index'])->name('staffings.index');
        Route::get('staffings/message', [StaffingController::class, 'getByMessageId'])->name('staffings.by-message');
        Route::get('staffings/{id}', [StaffingController::class, 'show'])->name('staffings.show');
    });

    // Write-protected endpoints (required write API key)
    Route::middleware('api.key:write')->group(function () {
        // Booking operations
        Route::post('staffing', [BookingController::class, 'store'])->name('staffing.book');
        Route::delete('staffing', [BookingController::class, 'destroy'])->name('staffing.unbook');
        
        // Staffing management
        Route::post('staffing/setup', [StaffingController::class, 'setup'])->name('staffing.setup');
        Route::patch('staffings/{id}/update', [StaffingController::class, 'update'])->name('staffings.update');
        Route::post('staffings/{id}/reset', [StaffingController::class, 'reset'])->name('staffings.reset');
    });
});