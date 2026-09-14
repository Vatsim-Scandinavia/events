<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');
    Route::inertia('settings/profile', 'settings/profile')->name('profile.edit');
    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
