<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ApiKeyAuthorizationException extends Exception
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
            'method' => request()->method(),
        ]);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): Response
    {
        return response([
            'error' => 'API_KEY_UNAUTHORIZED',
            'message' => 'The provided API key is not authorized to perform this action.',
        ], 403);
    }
}
