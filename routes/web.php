<?php

use App\Http\Controllers\Auth\OAuthController;
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

    Route::post('users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
    Route::delete('users/{user}/roles', [UserRoleController::class, 'destroy'])->name('users.roles.destroy');
});

require __DIR__.'/settings.php';
