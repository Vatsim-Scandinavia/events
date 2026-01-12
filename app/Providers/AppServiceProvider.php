<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redirect;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register redirect macros for flash messages
        Redirect::macro('withSuccess', function ($message) {
            return $this->with('success', $message);
        });

        Redirect::macro('withError', function ($message) {
            return $this->with('error', $message);
        });

        Redirect::macro('withWarning', function ($message) {
            return $this->with('warning', $message);
        });
    }
}
