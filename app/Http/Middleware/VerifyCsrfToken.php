<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
    
    /**
     * Determine if the session and input CSRF tokens match.
     * Disable CSRF verification in testing environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function tokensMatch($request)
    {
        // Disable CSRF in testing environment
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            return true;
        }
        
        return parent::tokensMatch($request);
    }
    
    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    protected function runningUnitTests()
    {
        return app()->runningUnitTests();
    }
    
    /**
     * Determine if the request should be excluded from CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        // Always exclude in testing
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return true;
        }
        
        return parent::inExceptArray($request);
    }
}
