<?php

namespace App\Integrations\ControlCenter;

use App\Integrations\Configuration;
use App\Integrations\ConnectionFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Control Center's API (v1), read with the hub's API key. Anything that goes wrong becomes a
 * ConnectionFailed with a message safe to show admins: it never contains the key.
 */
final class ControlCenterApi
{
    public function __construct(private Configuration $configuration) {}

    /**
     * Listing users needs a valid key even when asking for none of them.
     *
     * @throws ConnectionFailed
     */
    public function checkKey(): void
    {
        $this->get('users');
    }

    /**
     * Everyone in the vACC's training subdivisions, plus anyone holding an endorsement, with the
     * areas they're active in and their endorsements.
     *
     * @return list<array{cid: int, rating: ?string, areas: array<string, bool>, endorsements: array<string, mixed>}>
     *
     * @throws ConnectionFailed
     */
    public function members(): array
    {
        $users = $this->get('users', ['include' => ['allUsers', 'activeAreas', 'endorsements']]);

        return array_map(function (mixed $user) {
            if (! is_array($user) || ! is_int($user['id'] ?? null)
                || ! is_array($user['atc_active_areas'] ?? []) || ! is_array($user['endorsements'] ?? [])) {
                throw $this->unreadable();
            }

            return [
                'cid' => $user['id'],
                'rating' => is_string($user['rating'] ?? null) ? $user['rating'] : null,
                'areas' => array_change_key_case(array_map(fn (mixed $active) => $active === true, $user['atc_active_areas'] ?? [])),
                'endorsements' => $user['endorsements'] ?? [],
            ];
        }, $users);
    }

    /**
     * Every position Control Center knows: its callsign, name and frequency.
     *
     * @return list<array{callsign: string, name: ?string, frequency: ?string}>
     *
     * @throws ConnectionFailed
     */
    public function positions(): array
    {
        return array_map(function (mixed $position) {
            if (! is_array($position) || ! is_string($position['callsign'] ?? null)) {
                throw $this->unreadable();
            }

            return [
                'callsign' => trim($position['callsign']),
                'name' => is_string($position['name'] ?? null) ? mb_substr(trim($position['name']), 0, 100) : null,
                'frequency' => is_string($position['frequency'] ?? null) ? trim($position['frequency']) : null,
            ];
        }, $this->get('positions'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<mixed>
     *
     * @throws ConnectionFailed
     */
    private function get(string $path, array $query = []): array
    {
        $url = rtrim((string) $this->configuration->get('url'), '/')."/api/v1/{$path}";

        try {
            $response = Http::withToken((string) $this->configuration->get('token'))
                ->acceptJson()
                ->timeout(30)
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new ConnectionFailed('Control Center didn’t answer. Check its URL, or try again later.');
        }

        $this->guard($response);
        $data = $response->json('data');

        if (! is_array($data) || ! array_is_list($data)) {
            throw $this->unreadable();
        }

        return $data;
    }

    private function guard(Response $response): void
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ConnectionFailed('Control Center turned the API key down.');
        }

        if ($response->failed()) {
            throw new ConnectionFailed("Control Center answered with an error (HTTP {$response->status()}).");
        }
    }

    private function unreadable(): ConnectionFailed
    {
        return new ConnectionFailed('Control Center sent something the Hub couldn’t read.');
    }
}
