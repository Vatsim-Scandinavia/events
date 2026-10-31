<?php

namespace App\Integrations\Discord;

use RuntimeException;

/**
 * Discord turned a webhook call down for good, such as for a deleted webhook: trying again
 * won't help. The code is Discord's error code. The message is shown to staff, so it must never
 * contain the webhook.
 */
final class WebhookRefused extends RuntimeException {}
