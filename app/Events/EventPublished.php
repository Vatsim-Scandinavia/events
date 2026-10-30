<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A Draft was published, for the first time. Fired once the change is committed, for
 * Integrations to follow (see ADR 0005); the core itself never listens.
 */
final class EventPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Event $event) {}
}
