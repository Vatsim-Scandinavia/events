<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Integration;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Integration::captureUnhandledException($e);
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof EventException) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $exception->getMessage()], $exception->getCode() ?: 500);
            }

            if($exception->getRoute()) {
                return redirect()->route($exception->getRoute())->withErrors(['error' => $exception->getMessage()]);
            }

            return redirect()->back()->withErrors(['error' => $exception->getMessage()]);
        }

        return parent::render($request, $exception);
    }
}
