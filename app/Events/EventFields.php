<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Validation\ValidationException;

/**
 * An Integration feature that keeps details of its own about each Event, entered on the Event
 * form by whoever can manage the Event, such as the Discord webhook its roster is shown
 * through. The feature stores the values in its own tables. See ADR 0005.
 */
interface EventFields
{
    /**
     * The fields to show, with their current values for the Event (null while it's being
     * created).
     *
     * @return list<EventField>
     */
    public function eventFields(?Event $event): array;

    /**
     * Keep what was entered, as part of saving the Event. A value left empty, or a field not
     * sent, keeps what it was.
     *
     * @param  array<string, string>  $values  The values entered, by field key, already validated.
     * @param  list<string>  $removed  Keys of the fields staff cleared.
     *
     * @throws ValidationException when a value turns out not to work, keyed
     *                             "integration_fields.{key}"; the Event isn't saved then
     */
    public function saveEventFields(Event $event, array $values, array $removed): void;
}
