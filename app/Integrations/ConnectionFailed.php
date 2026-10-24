<?php

namespace App\Integrations;

use RuntimeException;

/**
 * The external system couldn't be reached, or refused the settings. The message is shown to
 * admins, so it must never contain a secret.
 */
final class ConnectionFailed extends RuntimeException {}
