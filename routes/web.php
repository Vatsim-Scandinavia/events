<?php

use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [OAuthController::class, 'login'])->name('login');
    Route::get('auth/redirect', [OAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('oauth.redirect');
    Route::get('auth/callback', [OAuthController::class, 'callback'])->middleware('throttle:10,1')->name('oauth.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [OAuthController::class, 'logout'])->name('logout');
    Route::redirect('dashboard', '/')->name('dashboard');
});

require __DIR__.'/settings.php';
