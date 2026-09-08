<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventCollaborationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageOwner', $this->route('event')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        return ['team_id' => ['required', 'integer', Rule::exists(Team::class, 'id'), Rule::notIn([$event->owner_team_id])]];
    }
}
