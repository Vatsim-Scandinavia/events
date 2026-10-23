<?php

use App\Http\Controllers\Admin\EventTypeController;
use App\Http\Controllers\Admin\FirController;
use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DevLoginController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftBookingController;
use App\Http\Controllers\Staff\EventCancellationController;
use App\Http\Controllers\Staff\EventManagementController;
use App\Http\Controllers\Staff\RosterController;
use App\Http\Controllers\Staff\StaffingController;
use App\Http\Controllers\StaffingInterestController;
use App\Http\Controllers\VatsimConnectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event')->name('events.show');

Route::middleware('guest')->group(function () {
    Route::middleware('throttle:sign-in')->group(function () {
        Route::get('/auth/vatsim', [VatsimConnectController::class, 'redirect'])->name('auth.vatsim');
        Route::get('/auth/vatsim/callback', [VatsimConnectController::class, 'callback'])->name('auth.vatsim.callback');
    });

    // Development sign-in as any user; returns 404 unless auth.dev_login is on.
    Route::get('/login', [DevLoginController::class, 'show'])->name('login');
    Route::post('/login/{user}', [DevLoginController::class, 'store'])->middleware('throttle:sign-in')->name('login.store');
});

Route::middleware(['auth', 'throttle:signed-in'])->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('/occurrences/{occurrence}/shifts/{shift}/booking', [ShiftBookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{booking}', [ShiftBookingController::class, 'destroy'])->name('bookings.destroy');

    Route::put('/occurrences/{occurrence}/interest', [StaffingInterestController::class, 'update'])->name('interest.update');
    Route::delete('/occurrences/{occurrence}/interest', [StaffingInterestController::class, 'destroy'])->name('interest.destroy');

    Route::middleware('can:manage-events')->group(function () {
        Route::get('/events/create', [EventManagementController::class, 'create'])->name('events.create');
        Route::post('/events', [EventManagementController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [EventManagementController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventManagementController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [EventManagementController::class, 'destroy'])->name('events.destroy');
        // The dates a schedule gives a new or existing event, previewed on the form.
        Route::post('/events/schedule', [EventManagementController::class, 'preview'])->name('events.schedule');
        Route::post('/events/{event}/schedule', [EventManagementController::class, 'preview'])->name('events.edit-schedule');

        Route::post('/events/{event}/cancel', [EventCancellationController::class, 'cancelEvent'])->name('events.cancel');
        Route::delete('/events/{event}/cancel', [EventCancellationController::class, 'restoreEvent'])->name('events.restore');
        Route::post('/occurrences/{occurrence}/cancel', [EventCancellationController::class, 'cancelOccurrence'])->name('occurrences.cancel');
        Route::delete('/occurrences/{occurrence}/cancel', [EventCancellationController::class, 'restoreOccurrence'])->name('occurrences.restore');
    });

    Route::middleware('can:manage-staffing')->group(function () {
        Route::get('/events/{event}/staffing', [StaffingController::class, 'edit'])->name('staffing.edit');
        Route::put('/events/{event}/staffing', [StaffingController::class, 'update'])->name('staffing.update');
        Route::put('/events/{event}/staffing/plan', [StaffingController::class, 'plan'])->name('staffing.plan');

        Route::post('/occurrences/{occurrence}/roster/publish', [RosterController::class, 'publish'])->name('roster.publish');
        Route::delete('/occurrences/{occurrence}/roster/publish', [RosterController::class, 'unpublish'])->name('roster.unpublish');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:manage-users')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'edit'])->name('users.edit');
            Route::post('/users/{user}/roles', [RoleAssignmentController::class, 'store'])->name('users.roles.store');
            Route::delete('/users/{user}/roles/{roleAssignment}', [RoleAssignmentController::class, 'destroy'])->scopeBindings()->name('users.roles.destroy');
        });

        Route::middleware('can:manage-roles')->group(function () {
            Route::resource('roles', RoleController::class)->except('show');
        });

        Route::middleware('can:manage-settings')->group(function () {
            Route::get('/settings', [SiteSettingsController::class, 'edit'])->name('settings.edit');
            // POST, as the form is sent as multipart when it carries a logo.
            Route::post('/settings', [SiteSettingsController::class, 'update'])->name('settings.update');

            Route::resource('firs', FirController::class)->except('show');
            Route::resource('event-types', EventTypeController::class)->except('show');
        });
    });
});
