<?php

namespace App\Integrations\Discord;

use App\Integrations\ConnectionFailed;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deletes a Roster message through the webhook it was posted with, after that webhook was
 * replaced or removed on its Event. Encrypted on the queue, as it carries the webhook.
 */
class DeleteRosterMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public function __construct(public string $webhook, public string $messageId) {}

    /**
     * A webhook Discord no longer knows has nothing left to delete.
     *
     * @throws ConnectionFailed to try again later
     */
    public function handle(): void
    {
        try {
            (new Webhook($this->webhook))->delete($this->messageId);
        } catch (WebhookRefused) {
            //
        }
    }
}
