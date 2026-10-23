<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Http\Requests\EventScheduleRequest;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventType;
use App\Models\Fir;
use App\Services\EventBanners;
use App\Staffing\EventSchedule;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Event Staff's pages for creating, editing and deleting events.
 */
class EventManagementController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Events/Form', [
            'event' => null,
            'types' => $this->types(),
            'firs' => $this->firs(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(EventRequest $request, EventSchedule $schedule, EventBanners $banners): RedirectResponse
    {
        $dates = $request->plan($schedule, null);
        $banner = $request->hasFile('banner') ? $banners->store($request->file('banner')) : null;

        $event = $this->discardingBannerOnFailure($banners, $banner, fn () => DB::transaction(function () use ($request, $dates, $banner) {
            // Creating the event adds its first date; the repeats follow.
            $event = Event::create([
                ...$request->details(),
                ...$request->repeatRule(),
                ...$request->firstSpan(),
                'banner_path' => $banner,
            ]);

            $dates->slice(1)->each(fn (array $date) => $event->occurrences()->create([
                'starts_at' => $date['starts_at'],
                'ends_at' => $date['ends_at'],
            ]));

            return $event;
        }));

        Inertia::flash('success', 'Draft created. Publish it when it’s ready for everyone to see.');

        return redirect("/events/{$event->id}");
    }

    public function edit(Event $event, EventBanners $banners): Response
    {
        Gate::authorize('update', $event);

        $occurrences = $event->occurrences()->withCount(['bookings', 'interests', 'rosterEntries'])->get();
        // Staff see and edit times in the zone they scheduled the event in.
        $local = fn (CarbonImmutable $moment) => $moment->setTimezone($event->timezone);
        $zone = fn (CarbonImmutable $moment) => $event->timezone === 'UTC' ? 'z' : ' '.$local($moment)->format('T');

        return Inertia::render('Events/Form', [
            'event' => [
                'id' => $event->id,
                'firId' => $event->fir_id,
                'name' => $event->name,
                'typeId' => $event->event_type_id,
                'description' => $event->description,
                'airports' => $event->airports,
                'routes' => $event->routes ?? [],
                'bannerUrl' => $banners->url($event->banner_path),
                'bookingUrl' => $event->booking_url,
                'briefingUrl' => $event->briefing_url,
                'startsAt' => $local($event->starts_at)->format('Y-m-d H:i'),
                'endsAt' => $local($event->ends_at)->format('Y-m-d H:i'),
                'timezone' => $event->timezone,
                'repeats' => $event->repeats->value,
                'repeatCount' => $event->repeat_count,
                'repeatUntil' => $event->repeat_until?->toDateString(),
                'cancelled' => $event->isCancelled(),
                'dates' => $occurrences->map(fn (EventOccurrence $occurrence) => [
                    'id' => $occurrence->id,
                    'date' => $local($occurrence->starts_at)->toDateString(),
                    'time' => $local($occurrence->starts_at)->format('H:i').'–'.$local($occurrence->ends_at)->format('H:i').$zone($occurrence->starts_at),
                    'hasStarted' => $occurrence->hasStarted(),
                    'hasEnded' => $occurrence->hasEnded(),
                    // Cancelled on its own; the event's own cancellation is `cancelled` above.
                    'cancelled' => $occurrence->cancelled_at !== null,
                    'bookings' => $occurrence->bookings_count,
                    'interests' => $occurrence->interests_count,
                    'roster' => $occurrence->roster_entries_count,
                ])->values(),
            ],
            'types' => $this->types(),
            'firs' => $this->firs(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(EventRequest $request, Event $event, EventSchedule $schedule, EventBanners $banners): RedirectResponse
    {
        $dates = $request->plan($schedule, $event);
        $previous = $event->banner_path;
        $banner = match (true) {
            $request->hasFile('banner') => $banners->store($request->file('banner')),
            $request->boolean('remove_banner') => null,
            default => $previous,
        };

        $uploaded = $request->hasFile('banner') ? $banner : null;

        $this->discardingBannerOnFailure($banners, $uploaded, fn () => DB::transaction(function () use ($request, $event, $schedule, $dates, $banner) {
            $event->update([...$request->details(), ...$request->repeatRule(), ...$request->firstSpan(), 'banner_path' => $banner]);
            $schedule->save($event, $dates);
        }));

        if ($banner !== $previous) {
            $banners->delete($previous);
        }

        Inertia::flash('success', 'Event saved.');

        return redirect("/events/{$event->id}");
    }

    /**
     * The dates a schedule would give the event, so the form can say what saving will
     * move or delete. Nothing is saved; a schedule that can't be saved fails the same way.
     */
    public function preview(EventScheduleRequest $request, EventSchedule $schedule, ?Event $event = null): JsonResponse
    {
        $planned = $request->plan($schedule, $event);
        $timezone = $request->firstSpan()['timezone'];

        return response()->json([
            'dates' => $planned->map(fn (array $date) => ['date' => $date['starts_at']->setTimezone($timezone)->toDateString(), 'id' => $date['id']])->values(),
            'removed' => $event ? $schedule->removed($event->occurrences()->get(), $planned)->pluck('id') : [],
        ]);
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        Inertia::flash('success', "{$event->name} was deleted.");

        return redirect('/events');
    }

    /**
     * Run the save, deleting a just-uploaded banner if it fails so no file is left behind.
     *
     * @template T
     *
     * @param  callable(): T  $save
     * @return T
     */
    private function discardingBannerOnFailure(EventBanners $banners, ?string $uploaded, callable $save): mixed
    {
        try {
            return $save();
        } catch (Throwable $e) {
            $banners->delete($uploaded);

            throw $e;
        }
    }

    /**
     * The FIRs the signed-in user may add events to.
     *
     * @return list<array{id: int, icao: string, name: string}>
     */
    private function firs(): array
    {
        return Fir::query()->orderBy('icao')->get(['id', 'icao', 'name'])
            ->filter(fn (Fir $fir) => Gate::allows('manage-events', $fir))
            ->values()
            ->toArray();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function types(): array
    {
        return EventType::query()->orderBy('name')->get(['id', 'name'])->toArray();
    }
}
