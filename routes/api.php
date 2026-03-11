<?php

use App\Http\Controllers\Api\DiscordController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v2', 'as' => 'api.v2.'], function () {
    Route::get('/discord/channels', [DiscordController::class, 'getChannels'])->name('discord.channels');
});