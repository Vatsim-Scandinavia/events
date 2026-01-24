<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvalidApiKeyException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        Log::channel('api')->warning('Invalid API Key used in request.', [
            'ip' => request()->ip(),
            'key_provided' => str(request()->header('X-API-KEY'))->mask('*', 4),
            'url' => request()->fullUrl(),
        ]);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request)
    {
        return response([
            'error' => 'API_KEY_INVALID',
            'message' => 'The provided API key is invalid.',
        ], 401);
    }
}
