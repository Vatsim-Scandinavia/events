<?php

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function __construct(private RecurrenceService $recurrenceService) {}

    public function run(): void
    {
        $admin = User::where('email', 'seed@vatsca.org')->firstOrFail();

        $calendars = Calendar::all()->keyBy('title');
        $vatsca    = $calendars['VATSIM Scandinavia'];
        $dk        = $calendars['VACC Denmark'];
        $no        = $calendars['VACC Norway'];
        $se        = $calendars['VACC Sweden'];
        $fi        = $calendars['VACC Finland'];
        $is        = $calendars['VACC Iceland'];
        $staff     = $calendars['Staff Events'];

        // ── One-off published events ────────────────────────────────────────
        $this->oneOff(
            $vatsca,
            $admin,
            'Cross-border Scandinavia Event',
            'A large network event covering all of Scandinavia.',
            'Join controllers from Denmark, Norway, Sweden, Finland and Iceland for a massive Cross-border event.',
            ['EKCH', 'ENGM', 'ESSA', 'EFHK'],
            now()->addDays(10),
            now()->addDays(10)->addHours(4),
        );

        $this->oneOff(
            $dk,
            $admin,
            'Copenhagen Friday Rush',
            'Heavy traffic into and out of EKCH every Friday evening.',
            'Weekly Friday rush event at Copenhagen Airport. Expect delays!',
            ['EKCH'],
            now()->addDays(3),
            now()->addDays(3)->addHours(3),
        );

        $this->oneOff(
            $no,
            $admin,
            'Oslo Approach Marathon',
            'Continuous staffing of ENGM approach for 6 hours.',
            'Oslo Approach Marathon — radar and approach positions staffed for 6 consecutive hours.',
            ['ENGM'],
            now()->addDays(7),
            now()->addDays(7)->addHours(6),
        );

        $this->oneOff(
            $se,
            $admin,
            'ARN Arrival Rush',
            'Stockholm Arlanda arrival rush with full ATC coverage.',
            'Full ATC coverage at ESSA for an extended arrival rush period.',
            ['ESSA'],
            now()->addDays(14),
            now()->addDays(14)->addHours(3),
        );

        $this->oneOff(
            $fi,
            $admin,
            'Helsinki Gateway Event',
            'Fly to or from Helsinki during full ATC coverage.',
            'EFHK fully staffed from ground to area control. All traffic welcome.',
            ['EFHK'],
            now()->subDays(5),
            now()->subDays(5)->addHours(4), // past event
        );

        $this->oneOff(
            $is,
            $admin,
            'Reykjavik Oceanic Day',
            'Oceanic crossings via BIKF with full staffing.',
            'An ocean crossing event with BIRK and BIKF fully staffed.',
            ['BIKF'],
            now()->addDays(21),
            now()->addDays(21)->addHours(5),
        );

        // ── Draft ───────────────────────────────────────────────────────────
        $this->oneOff(
            $vatsca,
            $admin,
            'Summer Mega Event 2026',
            'Our biggest event of the year — coming soon.',
            'Details to be announced. Watch this space.',
            ['EKCH', 'ENGM', 'ESSA'],
            now()->addMonths(2),
            now()->addMonths(2)->addHours(6),
            status: 'draft',
        );

        // ── Recurring events ────────────────────────────────────────────────
        // Weekly — every Monday
        $this->recurring(
            $dk,
            $admin,
            'Denmark Weekly Event',
            'A weekly event at a Danish airport every Monday.',
            'Fly to Denmark every Monday for a guaranteed staffed experience.',
            ['EKCH', 'EKBI', 'EKOD'],
            now()->next('Monday'),
            now()->next('Monday')->addHours(3),
            'FREQ=WEEKLY;BYDAY=MO',
        );

        // Bi-weekly — every other Wednesday
        $this->recurring(
            $no,
            $admin,
            'Norway Bi-weekly Night',
            'Norwegian controllers staff up every other Wednesday evening.',
            'Full ATC coverage at ENGM and ENBR on alternating Wednesdays.',
            ['ENGM', 'ENBR'],
            now()->next('Wednesday'),
            now()->next('Wednesday')->addHours(3),
            'FREQ=WEEKLY;INTERVAL=2;BYDAY=WE',
        );

        // Monthly — first Thursday
        $this->recurring(
            $vatsca,
            $admin,
            'Scandinavia Monthly Fly-in',
            'Monthly fly-in rotating around the Scandinavian capitals.',
            'On the first Thursday of each month we host a mega fly-in across the region.',
            ['EKCH', 'ENGM', 'ESSA', 'EFHK'],
            now()->next('Thursday'),
            now()->next('Thursday')->addHours(4),
            'FREQ=MONTHLY',
        );

        // Weekly staff training (private)
        $this->recurring(
            $staff,
            $admin,
            'Weekly Staff Sync',
            'Internal weekly staff meeting.',
            'Weekly sync for VATSCA board and division staff.',
            [],
            now()->next('Tuesday'),
            now()->next('Tuesday')->addHours(1),
            'FREQ=WEEKLY;BYDAY=TU',
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function oneOff(
        Calendar $calendar,
        User $creator,
        string $title,
        string $short,
        string $long,
        array $airports,
        Carbon $start,
        Carbon $end,
        string $status = 'published',
    ): Event {
        $event = Event::create([
            'calendar_id'       => $calendar->id,
            'title'             => $title,
            'slug'              => Str::slug($title),
            'short_description' => $short,
            'long_description'  => $long,
            'featured_airports' => $airports,
            'status'            => $status,
            'recurrence_rule'   => null,
            'timezone'          => 'UTC',
            'created_by'        => $creator->id,
        ]);

        EventOccurrence::create([
            'event_id'   => $event->id,
            'start_time' => $start,
            'end_time'   => $end,
            'status'     => $start->isPast() ? 'completed' : 'scheduled',
        ]);

        return $event;
    }

    private function recurring(
        Calendar $calendar,
        User $creator,
        string $title,
        string $short,
        string $long,
        array $airports,
        Carbon $firstStart,
        Carbon $firstEnd,
        string $rule,
    ): Event {
        $event = Event::create([
            'calendar_id'       => $calendar->id,
            'title'             => $title,
            'slug'              => Str::slug($title),
            'short_description' => $short,
            'long_description'  => $long,
            'featured_airports' => $airports,
            'status'            => 'published',
            'recurrence_rule'   => $rule,
            'timezone'          => 'UTC',
            'created_by'        => $creator->id,
        ]);

        // Seed the base occurrence — RecurrenceService uses this as its template.
        EventOccurrence::create([
            'event_id'   => $event->id,
            'start_time' => $firstStart,
            'end_time'   => $firstEnd,
            'status'     => 'scheduled',
        ]);

        // Generate the full rolling horizon of future occurrences.
        $this->recurrenceService->generate($event);

        return $event;
    }
}
