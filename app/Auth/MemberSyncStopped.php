<?php

namespace App\Auth;

use RuntimeException;

/**
 * The daily sync can't go on today: a rate limit or an outage. The Accounts it didn't reach are
 * first in line tomorrow, as it goes least recently synced first.
 */
final class MemberSyncStopped extends RuntimeException {}
