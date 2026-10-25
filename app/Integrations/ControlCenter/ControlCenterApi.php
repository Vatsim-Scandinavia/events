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
     * Making bookings needs a key that can edit. An empty booking passes that check and then
     * fails validation, so nothing is booked.
     *
     * @throws ConnectionFailed
     */
    public function checkEditScope(): void
    {
        $response = $this->request('post', 'bookings/create');

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ConnectionFailed('The API key can only read. Booking push needs a key that can edit.');
        }

        if ($response->status() !== 422) {
            $this->guard($response);
        }
    }

    /**
     * @param  array<string, mixed>  $booking  As BookingPush::payload() makes it.
     * @return int The booking's id in Control Center.
     *
     * @throws ConnectionFailed when it's worth trying again later
     * @throws BookingRefused
     */
    public function createBooking(array $booking): int
    {
        $response = $this->request('post', 'bookings/create', $booking);

        if ($response->status() === 404) {
            throw new BookingRefused("Control Center doesn’t know controller {$booking['cid']}.");
        }

        $this->guardBooking($response);
        $id = $response->json('booking.id');

        if (! is_int($id)) {
            throw $this->unreadable();
        }

        return $id;
    }

    /**
     * Move a booking to new times or another position. Control Center can't hand it to someone
     * else; delete it and create another for that.
     *
     * @param  array<string, mixed>  $booking
     * @return bool False when it's no longer in Control Center.
     *
     * @throws ConnectionFailed
     * @throws BookingRefused
     */
    public function updateBooking(int $id, array $booking): bool
    {
        $response = $this->request('patch', "bookings/{$id}", $booking);

        if ($response->status() === 404) {
            return false;
        }

        $this->guardBooking($response);

        return true;
    }

    /**
     * A booking that's already gone counts as deleted.
     *
     * @throws ConnectionFailed
     * @throws BookingRefused
     */
    public function deleteBooking(int $id): void
    {
        $response = $this->request('delete', "bookings/{$id}");

        if ($response->status() !== 404) {
            $this->guardBooking($response);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<mixed>
     *
     * @throws ConnectionFailed
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->request('get', $path, $query);

        $this->guard($response);
        $data = $response->json('data');

        if (! is_array($data) || ! array_is_list($data)) {
            throw $this->unreadable();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ConnectionFailed
     */
    private function request(string $method, string $path, array $data = []): Response
    {
        $url = $this->baseUrl()."/api/v1/{$path}";

        try {
            return Http::withToken((string) $this->configuration->get('token'))
                ->acceptJson()
                ->timeout(30)
                ->{$method}($url, $data);
        } catch (ConnectionException) {
            throw new ConnectionFailed('Control Center didn’t answer. Check its URL, or try again later.');
        }
    }

    /**
     * Control Center's own address. Admins often paste its API's, so a trailing /api or /api/v1
     * is dropped rather than doubled.
     */
    private function baseUrl(): string
    {
        return (string) preg_replace('#/api(/v1)?$#i', '', rtrim((string) $this->configuration->get('url'), '/'));
    }

    /**
     * Server errors and a refused key are worth trying again; any other refusal says why the
     * booking can't be made as it is.
     *
     * @throws ConnectionFailed
     * @throws BookingRefused
     */
    private function guardBooking(Response $response): void
    {
        if ($response->serverError() || in_array($response->status(), [401, 403, 429], true)) {
            $this->guard($response);
        }

        if ($response->failed()) {
            $reason = $response->json('message');
            $reason = is_string($reason) && $reason !== '' ? $reason : "Control Center turned it down (HTTP {$response->status()}).";

            throw new BookingRefused($reason, retryable: str_starts_with($reason, 'VATSIM API error'));
        }
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
