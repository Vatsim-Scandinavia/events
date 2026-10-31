<?php

namespace App\Integrations\Discord;

use App\Integrations\ConnectionFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A Discord channel webhook, which can post messages and edit or delete the ones it posted.
 * Redirects are never followed, so it only ever reaches Discord. The URL is a secret, so it
 * never appears in what's thrown.
 */
final readonly class Webhook
{
    /** Discord's error codes for a message or webhook it doesn't know. */
    private const int UNKNOWN_MESSAGE = 10008;

    private const int UNKNOWN_WEBHOOK = 10015;

    public function __construct(private string $url) {}

    /**
     * The channel it posts in, and its name as Discord shows it.
     *
     * @return array{channel_id: string, name: ?string}
     *
     * @throws ConnectionFailed
     * @throws WebhookRefused when Discord doesn't know it
     */
    public function check(): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get($this->url));
        $channelId = $response->json('channel_id');

        if (! is_string($channelId)) {
            throw new ConnectionFailed('Discord’s answer couldn’t be read. Try again later.');
        }

        return ['channel_id' => $channelId, 'name' => is_string($response->json('name')) ? $response->json('name') : null];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id: string, channel_id: ?string, name: ?string} the new message, with where it was posted and as whom
     *
     * @throws ConnectionFailed
     * @throws WebhookRefused
     */
    public function post(array $message): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post("{$this->url}?wait=true", $message));
        $id = $response->json('id');

        if (! is_string($id)) {
            throw new ConnectionFailed('Discord’s answer couldn’t be read. Try again later.');
        }

        return [
            'id' => $id,
            'channel_id' => is_string($response->json('channel_id')) ? $response->json('channel_id') : null,
            'name' => is_string($response->json('author.username')) ? $response->json('author.username') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return bool false when the message is gone, deleted in Discord
     *
     * @throws ConnectionFailed
     * @throws WebhookRefused
     */
    public function edit(string $messageId, array $message): bool
    {
        return $this->sendToMessage(fn (PendingRequest $http) => $http->patch("{$this->url}/messages/{$messageId}", $message));
    }

    /**
     * Deletes the message; one that's already gone is fine.
     *
     * @throws ConnectionFailed
     * @throws WebhookRefused
     */
    public function delete(string $messageId): void
    {
        $this->sendToMessage(fn (PendingRequest $http) => $http->delete("{$this->url}/messages/{$messageId}"));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws ConnectionFailed
     * @throws WebhookRefused
     */
    private function sendToMessage(callable $request): bool
    {
        try {
            $this->send($request);
        } catch (WebhookRefused $refused) {
            if ($refused->getCode() === self::UNKNOWN_MESSAGE) {
                return false;
            }

            throw $refused;
        }

        return true;
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws ConnectionFailed when it's worth trying again later
     * @throws WebhookRefused when it isn't
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request(Http::acceptJson()->withoutRedirecting()->timeout(10));
        } catch (ConnectionException) {
            throw new ConnectionFailed('Discord didn’t answer. Try again later.');
        }

        if ($response->successful()) {
            return $response;
        }

        $code = is_int($response->json('code')) ? $response->json('code') : null;

        if ($response->unauthorized() || $code === self::UNKNOWN_WEBHOOK || ($response->notFound() && $code !== self::UNKNOWN_MESSAGE)) {
            throw new WebhookRefused('Discord doesn’t know the webhook. It may have been deleted; copy a new one.', self::UNKNOWN_WEBHOOK);
        }

        if ($response->status() === 429 || $response->serverError() || $response->redirect()) {
            throw new ConnectionFailed("Discord couldn’t take it (HTTP {$response->status()}). Try again later.");
        }

        throw new WebhookRefused("Discord turned it down (HTTP {$response->status()}).", $code ?? 0);
    }
}
