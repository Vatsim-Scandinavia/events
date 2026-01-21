<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public endpoints (optional API key)
    Route::middleware('api.key.optional')->group(function () {
        Route::get('events', [ApiController::class, 'events'])->name('events');
        Route::get('events/{id}', [ApiController::class, 'event'])->name('event');
    });

    // Protected endpoints (required API key)
    Route::middleware('api.key')->group(function () {
        Route::get('events/{id}/staffing', [ApiController::class, 'staffing'])->name('event.staffing');
    });

    Route::middleware('api.key:write')->group(function () {
        // Write-protected endpoints (required write API key)
    });
});