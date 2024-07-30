<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => ['api-token:edit']], function() {
    // Calendars
    Route::post('/calendars', [App\Http\Controllers\API\CalendarController::class, 'store'])->name('api.calendars.store');
    Route::patch('/calendars/{calendar}', [App\Http\Controllers\API\CalendarController::class, 'update'])->name('api.calendars.update');
    Route::delete('/calendars/{calendar}', [App\Http\Controllers\API\CalendarController::class, 'destroy'])->name('api.calendars.destroy');

    // Events
    Route::post('/events', [App\Http\Controllers\API\EventController::class, 'store'])->name('api.event.store');
    Route::patch('/events/{event}', [App\Http\Controllers\API\EventController::class, 'update'])->name('api.event.update');
    Route::delete('/events/{event}', [App\Http\Controllers\API\EventController::class, 'destroy'])->name('api.event.destroy');
});

Route::get('/calendars/{calendar}/events', [App\Http\Controllers\API\EventController::class, 'index'])->name('api.event.index');

Route::group(['middleware' => ['api-token']], function() {
    // Calendars
    Route::get('/calendars', [App\Http\Controllers\API\CalendarController::class, 'index'])->name('api.calendars.index');
    Route::get('/calendars/{calendar}', [App\Http\Controllers\API\CalendarController::class, 'show'])->name('api.calendars.show');
    
    // Events
    Route::get('/events/{event}', [App\Http\Controllers\API\EventController::class, 'show'])->name('api.event.show');
});
