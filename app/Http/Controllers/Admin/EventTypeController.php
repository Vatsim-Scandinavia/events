<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets admins manage the event types staff choose from.
 *
 * Deleting a type leaves its events without one, so they show as General.
 */
class EventTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/EventTypes/Index', [
            'types' => EventType::query()->withCount('events')->orderBy('name')->get()->map(fn (EventType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'events' => $type->events_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/EventTypes/Form', ['type' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = EventType::create($this->validated($request, null));

        Inertia::flash('success', "{$type->name} was added.");

        return redirect('/admin/event-types');
    }

    public function edit(EventType $eventType): Response
    {
        return Inertia::render('Admin/EventTypes/Form', [
            'type' => [
                'id' => $eventType->id,
                'name' => $eventType->name,
                'events' => $eventType->events()->count(),
            ],
        ]);
    }

    public function update(Request $request, EventType $eventType): RedirectResponse
    {
        $eventType->update($this->validated($request, $eventType));

        Inertia::flash('success', "{$eventType->name} was saved.");

        return redirect('/admin/event-types');
    }

    public function destroy(EventType $eventType): RedirectResponse
    {
        $eventType->delete();

        Inertia::flash('success', "{$eventType->name} was deleted.");

        return redirect('/admin/event-types');
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, ?EventType $eventType): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('event_types', 'name')->ignore($eventType)],
        ], [
            'name.required' => 'Enter the type’s name.',
            'name.unique' => 'There’s already a type with this name.',
        ]);
    }
}
