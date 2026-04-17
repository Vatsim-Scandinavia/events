<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ControlCenterClient
{
    protected ?string $apiUrl;
    protected ?string $apiToken;

    public function __construct()
    {
        $this->apiUrl   = config('services.control_center.api_url');
        $this->apiToken = config('services.control_center.api_token');
    }

    /**
     * Perform an authenticated GET request to the Control Center API.
     *
     * @param string $path  API path relative to the base URL (e.g. '/v1/positions')
     * @param array  $query Optional query parameters
     * @return array|null   Decoded response body, or null on failure
     */
    public function get(string $path, array $query = []): ?array
    {
        if (!$this->apiUrl || !$this->apiToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->get($this->apiUrl . $path, $query);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('ControlCenter API GET failed', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        } catch (\Throwable $th) {
            report($th);
            return null;
        }
    }

    /**
     * Perform an authenticated POST request to the Control Center API.
     *
     * @param string $path    API path relative to the base URL
     * @param array  $payload Request body
     * @return array|null     Decoded response body, or null on failure
     */
    public function post(string $path, array $payload): ?array
    {
        if (!$this->apiUrl || !$this->apiToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->post($this->apiUrl . $path, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('ControlCenter API POST failed', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        } catch (\Throwable $th) {
            report($th);
            return null;
        }
    }

    /**
     * Perform an authenticated DELETE request to the Control Center API.
     *
     * @param string $path API path relative to the base URL
     * @return bool        True if the request succeeded
     */
    public function delete(string $path): bool
    {
        if (!$this->apiUrl || !$this->apiToken) {
            return false;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->delete($this->apiUrl . $path);

            if ($response->successful()) {
                return true;
            }

            Log::warning('ControlCenter API DELETE failed', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        } catch (\Throwable $th) {
            report($th);
            return false;
        }
    }
}
