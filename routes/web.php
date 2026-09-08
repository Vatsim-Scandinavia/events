<?php

use App\Http\Controllers\AirportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\EventBannerController;
use App\Http\Controllers\EventCancellationController;
use App\Http\Controllers\EventCollaborationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FirController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [OAuthController::class, 'index'])->name('login');
    Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')->name('oauth.redirect');
    Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])
        ->middleware('throttle:10,1')->name('oauth.callback');
});

Route::post('logout', [OAuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::resource('firs', FirController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('events', EventController::class)->except(['destroy']);
    Route::get('events/{event}/banner', EventBannerController::class)->name('events.banner');
    Route::post('events/{event}/cancellations', [EventCancellationController::class, 'store'])->name('events.cancellations.store');
    Route::post('events/{event}/collaborations', [EventCollaborationController::class, 'store'])->name('events.collaborations.store');
    Route::patch('events/{event}/collaborations/{collaboration}', [EventCollaborationController::class, 'update'])->name('events.collaborations.update');
    Route::delete('events/{event}/collaborations/{collaboration}', [EventCollaborationController::class, 'destroy'])->name('events.collaborations.destroy');
    Route::resource('airports', AirportController::class)->only(['index', 'store']);

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
    Route::delete('users/{user}/roles', [UserRoleController::class, 'destroy'])->name('users.roles.destroy');
});

require __DIR__.'/settings.php';
