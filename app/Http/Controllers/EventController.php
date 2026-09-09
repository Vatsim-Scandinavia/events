<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RenderEventMarkdown;
use App\Actions\SaveEvent;
use App\Http\Requests\EventIndexRequest;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\Team;
use App\PermissionName;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private RenderEventMarkdown $markdown) {}

    public function index(EventIndexRequest $request): Response
    {
        $filters = ['search' => $request->validated('search') ?? '', 'status' => $request->validated('status') ?? ''];
        $events = Event::visibleTo($request->user())->with(['owner', 'airports'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->whereLike('title', '%'.$filters['search'].'%'))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(12)->withQueryString()
            ->through(fn (Event $event): array => [
                ...$event->only(['id', 'title', 'short_description', 'status', 'timezone', 'recurrence']),
                'short_description_html' => $this->markdown->handle($event->short_description),
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'banner_url' => $event->banner_path === null ? null : route('events.banner', $event),
                'owner' => $event->owner->only(['id', 'code', 'name']),
                'airports' => $event->airports->map->only(['id', 'icao', 'name', 'country']),
            ]);

        $invitations = EventCollaboration::whereNull('accepted_at')
            ->whereIn('team_id', $request->user()->teamsWithPermission(PermissionName::ManageEvents)->select('id'))
            ->with(['event.owner', 'team'])->orderBy('id')->get()
            ->map(fn (EventCollaboration $invitation): array => [
                'id' => $invitation->id, 'event_id' => $invitation->event_id,
                'owner_code' => $invitation->event->owner->code, 'team_code' => $invitation->team->code,
            ]);

        return Inertia::render('events/index', [
            'events' => $events, 'filters' => $filters, 'invitations' => $invitations,
            'can_create' => $request->user()->can('create', Event::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('events/form', [
            'event' => null,
            'firs' => $request->user()->teamsWithPermission(PermissionName::ManageEvents)->orderBy('code')->get(['id', 'code', 'name']),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(EventRequest $request, SaveEvent $save): RedirectResponse
    {
        $event = $save->handle($request);

        return to_route('events.show', $event);
    }

    public function show(EventIndexRequest $request, Event $event, EventSchedule $schedule): Response
    {
        abort_unless(Event::visibleTo($request->user())->whereKey($event->id)->exists(), 404);
        $event->load(['owner', 'airports', 'cancellations', 'collaborations.team', 'roster']);
        $currentTime = CarbonImmutable::now($event->timezone);
        $from = $request->validated('from') ?? $currentTime->toDateString();
        $occurrences = $schedule->upcoming($event, $from, 13, $request->filled('from') ? null : $currentTime);
        $nextFrom = count($occurrences) > 12 ? $occurrences[12]['date'] : null;

        return Inertia::render('events/show', [
            'event' => $this->details($event),
            'description_html' => $this->markdown->handle($event->description),
            'occurrences' => array_slice($occurrences, 0, 12),
            'from' => $from, 'next_from' => $nextFrom,
            'collaborations' => $event->collaborations->map(fn (EventCollaboration $collaboration): array => [
                'id' => $collaboration->id, 'team' => $collaboration->team->only(['id', 'code', 'name']),
                'accepted' => $collaboration->accepted_at !== null,
            ]),
            'firs' => $request->user()->can('manageOwner', $event)
                ? Team::where('id', '!=', $event->owner_team_id)->whereNotIn('id', $event->collaborations->pluck('team_id'))->orderBy('code')->get(['id', 'code', 'name'])
                : [],
            'can' => [
                'edit' => $event->status === 'draft' && $request->user()->can('update', $event),
                'manage_owner' => $request->user()->can('manageOwner', $event),
            ],
        ]);
    }

    public function edit(Request $request, Event $event): Response
    {
        abort_unless(Event::visibleTo($request->user())->whereKey($event->id)->exists(), 404);
        Gate::authorize('update', $event);
        abort_if($event->status === 'cancelled', 409, 'Cancelled events cannot be edited.');

        return Inertia::render('events/form', [
            'event' => $this->details($event->load(['owner', 'airports'])),
            'firs' => [$event->owner->only(['id', 'code', 'name'])],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(EventRequest $request, Event $event, SaveEvent $save): RedirectResponse
    {
        $save->handle($request, $event);

        return to_route('events.show', $event);
    }

    /** @return array<string, mixed> */
    private function details(Event $event): array
    {
        return [
            ...$event->only(['id', 'owner_team_id', 'title', 'short_description', 'description', 'timezone', 'local_start', 'local_end', 'recurrence', 'recurrence_interval', 'monthly_week', 'status', 'cancellation_reason']),
            'short_description_html' => $this->markdown->handle($event->short_description),
            'recurrence_until' => $event->recurrence_until?->toDateString(),
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'owner' => $event->owner->only(['id', 'code', 'name']),
            'airports' => $event->airports->map->only(['id', 'icao', 'name', 'country']),
            'banner_url' => $event->banner_path === null ? null : route('events.banner', $event),
            'roster_exists' => $event->roster()->exists(),
            'schedule_locked' => $event->cancellations()->exists() || $event->roster()->exists(),
        ];
    }
}
