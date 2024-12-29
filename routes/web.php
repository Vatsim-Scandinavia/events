<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//--------------------------------------------------------------------------
// Homepage
//--------------------------------------------------------------------------
Route::get('/', [HomeController::class, 'index'])->name('home');

//--------------------------------------------------------------------------
// VATSIM Authentication
//--------------------------------------------------------------------------
Route::get('/login', [LoginController::class, 'login'])->middleware('guest')->name('login');
Route::get('/validate', [LoginController::class, 'validateLogin'])->middleware('guest');
Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Auth::routes();

Route::middleware(['auth'])->group(function () {

    Route::controller(CalendarController::class)->group(function () {
        Route::get('/calendars', 'index')->name('calendars.index');
        Route::get('/calendars/create', 'create')->name('calendars.create');
        Route::get('/calendars/edit/{calendar}', 'edit')->name('calendars.edit');
        Route::post('/calendars', 'store')->name('calendars.store');
        Route::patch('/calendars/{calendar}', 'update')->name('calendars.update');
        Route::delete('/calendars', 'destroy')->name('calendars.destroy');
    });

    Route::controller(EventController::class)->group(function () {
        Route::get('/events', 'index')->name('events.index');
        Route::get('/events/create', 'create')->name('events.create');
        Route::get('/events/{event}/edit', 'edit')->name('events.edit');
        Route::post('/events', 'store')->name('events.store');
        Route::patch('/events/{event}', 'update')->name('events.update');
        Route::delete('/events/{event}', 'destroy')->name('events.destroy');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')->name('users.index');
        Route::get('/users/{user}', 'show')->name('users.show');
        Route::patch('/users/{user}', 'update')->name('users.update');
    });

    // Route::get('/staffings', [App\Http\Controllers\StaffingController::class, 'index'])->name('staffings.index');
    // Route::get('/staffings/create', [App\Http\Controllers\StaffingController::class, 'create'])->name('staffings.create');
    // Route::get('/staffings/edit/{staffing}', [App\Http\Controllers\StaffingController::class, 'edit'])->name('staffings.edit');
    // Route::post('/staffings/store', [App\Http\Controllers\StaffingController::class, 'store'])->name('staffings.store');
    // Route::patch('/staffings/edit/{staffing}', [App\Http\Controllers\StaffingController::class, 'update'])->name('staffings.update');
    // Route::delete('/staffings/delete/{staffing}', [App\Http\Controllers\StaffingController::class, 'delete'])->name('staffings.delete');

});

Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/calendar/{calendar}', [CalendarController::class, 'show'])->name('calendar');
