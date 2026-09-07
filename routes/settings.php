<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
