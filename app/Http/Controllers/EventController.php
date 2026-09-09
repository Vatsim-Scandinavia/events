<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RenderEventMarkdown;
use App\Actions\SaveEvent;
use App\Http\Requests\EventIndexRequest;
use App\Http\Requests\EventRequest;
use App\Models\Airport;
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
    public function __construct(private RenderEventMarkdown $markdown, private EventSchedule $schedule) {}

    public function index(EventIndexRequest $request): Response
    {
        $user = $request->user();
        $filters = ['search' => trim($request->validated('search') ?? ''), 'status' => $request->validated('status') ?? ''];
        $events = Event::query()->where(function (Builder $query) use ($user): void {
            $query->whereIn('id', Event::publiclyVisible()->select('events.id'));
            if ($user !== null) {
                $query->orWhereIn('id', Event::visibleTo($user)->select('events.id'));
            }
        })->with(['owner', 'airports', 'cancellations'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->whereLike('title', '%'.$filters['search'].'%'))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(12)->withQueryString()
            ->through(fn (Event $event): array => $this->summary($event));
        $props = ['events' => $events, 'filters' => $filters];

        if ($user !== null && $user->can('viewAny', Event::class)) {
            $props['invitations'] = EventCollaboration::whereNull('accepted_at')
                ->whereIn('team_id', $user->teamsWithPermission(PermissionName::ManageEvents)->select('id'))
                ->with(['event.owner', 'team'])->orderBy('id')->get()
                ->map(fn (EventCollaboration $invitation): array => [
                    'id' => $invitation->id, 'event_id' => $invitation->event_id,
                    'owner_code' => $invitation->event->owner->code, 'team_code' => $invitation->team->code,
                ]);
            $props['can_create'] = $user->can('create', Event::class);
        }

        return Inertia::render('events/index', $props);
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

    public function show(EventIndexRequest $request, Event $event): Response
    {
        $user = $request->user();
        $canViewStaff = $user?->can('view', $event) ?? false;
        abort_unless($event->isPubliclyVisible() || $canViewStaff, 404);
        $event->load(['owner', 'airports', 'cancellations']);
        $currentTime = CarbonImmutable::now($event->timezone);
        $from = $request->validated('from') ?? $currentTime->toDateString();
        $occurrences = $this->schedule->upcoming($event, $from, 13, $request->filled('from') ? null : $currentTime);
        $props = [
            'event' => [
                ...$this->summary($event),
                'description_html' => $this->markdown->handle($event->description),
                'cancellation_reason' => $event->cancellation_reason,
            ],
            'occurrences' => array_slice($occurrences, 0, 12),
            'from' => $from,
            'next_from' => count($occurrences) > 12 ? $occurrences[12]['date'] : null,
        ];

        if ($user !== null && $canViewStaff) {
            $event->load(['collaborations.team', 'roster']);
            $props['event']['roster_enabled'] = $event->roster_enabled;
            $props['event']['roster_exists'] = $event->roster !== null;
            $props['collaborations'] = $event->collaborations->map(fn (EventCollaboration $collaboration): array => [
                'id' => $collaboration->id, 'team' => $collaboration->team->only(['id', 'code', 'name']),
                'accepted' => $collaboration->accepted_at !== null,
            ]);
            $props['firs'] = $user->can('manageOwner', $event)
                ? Team::where('id', '!=', $event->owner_team_id)->whereNotIn('id', $event->collaborations->pluck('team_id'))->orderBy('code')->get(['id', 'code', 'name'])
                : [];
            $props['can'] = [
                'edit' => $event->status !== 'cancelled' && $user->can('update', $event),
                'manage_owner' => $user->can('manageOwner', $event),
                'publish' => $event->status === 'draft' && $user->can('publish', $event),
                'unpublish' => $event->isPubliclyVisible() && $user->can('publish', $event),
            ];
        }

        return Inertia::render('events/show', $props);
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
    private function summary(Event $event): array
    {
        $now = CarbonImmutable::now($event->timezone);
        $occurrence = $this->schedule->upcoming($event, $now->toDateString(), 1, $now)[0] ?? null;

        return [
            ...$event->only(['id', 'title', 'status', 'timezone', 'local_start', 'recurrence', 'recurrence_interval', 'monthly_week']),
            'short_description_html' => $this->markdown->handle($event->short_description),
            'recurrence_until' => $event->recurrence_until?->toDateString(),
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'owner' => $event->owner->only(['id', 'code', 'name']),
            'airports' => $event->airports->map(fn (Airport $airport): array => $airport->only(['id', 'icao', 'name', 'country']))->all(),
            'banner_url' => $event->banner_path === null ? null : route('events.banner', $event),
            'occurrence' => $occurrence,
        ];
    }

    /** @return array<string, mixed> */
    private function details(Event $event): array
    {
        $roster = $event->roster;
        $hasSubmissions = $roster !== null && ($roster->bookings()->exists() || $roster->interests()->exists());
        $hasCancellations = $event->cancellations()->exists();

        return [
            ...$event->only(['id', 'owner_team_id', 'title', 'short_description', 'description', 'timezone', 'local_start', 'local_end', 'recurrence', 'recurrence_interval', 'monthly_week', 'roster_enabled', 'status', 'cancellation_reason']),
            'short_description_html' => $this->markdown->handle($event->short_description),
            'recurrence_until' => $event->recurrence_until?->toDateString(),
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'owner' => $event->owner->only(['id', 'code', 'name']),
            'airports' => $event->airports->map->only(['id', 'icao', 'name', 'country']),
            'banner_url' => $event->banner_path === null ? null : route('events.banner', $event),
            'roster_exists' => $roster !== null,
            'roster_toggle_locked' => $hasSubmissions,
            'schedule_locked' => $hasCancellations || ($event->roster_enabled && $roster !== null) || $hasSubmissions,
            'schedule_locked_by_cancellations' => $hasCancellations,
        ];
    }
}
