<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_upcoming_events_soonest_first(): void
    {
        Event::factory()->create(['name' => 'Later', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(3)]);
        Event::factory()->create(['name' => 'Sooner', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(3)]);
        Event::factory()->past()->create(['name' => 'Finished']);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events', 2)
                ->where('events.0.name', 'Sooner')
                ->where('events.1.name', 'Later')
            );
    }

    public function test_it_includes_events_that_are_in_progress(): void
    {
        Event::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

        $this->get('/events')->assertInertia(fn (Assert $page) => $page->has('events', 1));
    }

    public function test_an_event_stops_being_listed_the_moment_its_date_ends(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 21:00', 'UTC'));
        Event::factory()->create(['starts_at' => Carbon::parse('2026-07-01 18:00', 'UTC'), 'ends_at' => Carbon::parse('2026-07-01 21:00', 'UTC')]);

        $this->get('/events')->assertInertia(fn (Assert $page) => $page->has('events', 0));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('upcomingEvents', 0));
    }

    public function test_a_late_zulu_event_is_listed_on_the_next_day_for_viewers_further_east(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));
        Event::factory()->create([
            'starts_at' => Carbon::parse('2026-07-18 23:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-07-19 01:00', 'UTC'),
        ]);

        $this->get('/events')->assertInertia(fn (Assert $page) => $page
            ->where('events.0.day', 18)
            ->where('events.0.time', '23:00–01:00z')
        );

        $this->actingAs(User::factory()->withTimezone('Asia/Tokyo')->create())
            ->get('/events')
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.0.day', 19)
                ->where('events.0.date', 'Sun 19 Jul')
                ->where('events.0.time', '08:00–10:00 JST')
            );
    }

    public function test_it_lists_only_the_chosen_firs_events(): void
    {
        $denmark = Fir::factory()->create(['icao' => 'EKDK']);
        Event::factory()->for($denmark)->create(['name' => 'Copenhagen']);
        Event::factory()->create(['name' => 'Elsewhere']);

        $this->get('/events?fir=ekdk')->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Copenhagen')
            ->where('filters.fir', 'EKDK')
        );
    }

    public function test_an_unknown_fir_is_ignored(): void
    {
        Event::factory()->count(2)->create();

        $this->get('/events?fir=XXXX')->assertInertia(fn (Assert $page) => $page
            ->has('events', 2)
            ->where('filters.fir', null)
        );
    }

    public function test_it_lists_only_events_at_the_chosen_airport(): void
    {
        Event::factory()->create(['name' => 'Kastrup', 'airports' => ['EKCH', 'ESSA']]);
        Event::factory()->create(['name' => 'Billund', 'airports' => ['EKBI']]);

        $this->get('/events?airport=EKCH')->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Kastrup')
            ->where('filters.airport', 'EKCH')
        );
    }

    public function test_an_airport_with_no_events_is_kept_so_the_page_shows_none(): void
    {
        Event::factory()->create(['airports' => ['EKCH']]);

        $this->get('/events?airport=ENGM')->assertInertia(fn (Assert $page) => $page
            ->has('events', 0)
            ->where('filters.airport', 'ENGM')
            ->where('airports', ['EKCH', 'ENGM'])
        );
    }

    public function test_the_fir_and_airport_filters_combine(): void
    {
        $denmark = Fir::factory()->create(['icao' => 'EKDK']);
        Event::factory()->for($denmark)->create(['name' => 'Kastrup', 'airports' => ['EKCH']]);
        Event::factory()->for($denmark)->create(['name' => 'Billund', 'airports' => ['EKBI']]);
        Event::factory()->create(['name' => 'Arlanda', 'airports' => ['EKCH']]);

        $this->get('/events?fir=EKDK&airport=EKCH')->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Kastrup')
        );
    }

    public function test_the_airports_to_choose_from_follow_the_chosen_fir(): void
    {
        $denmark = Fir::factory()->create(['icao' => 'EKDK']);
        Event::factory()->for($denmark)->create(['airports' => ['EKCH', 'EKBI']]);
        Event::factory()->create(['airports' => ['ESSA']]);

        $this->get('/events')->assertInertia(fn (Assert $page) => $page->where('airports', ['EKBI', 'EKCH', 'ESSA']));
        $this->get('/events?fir=EKDK')->assertInertia(fn (Assert $page) => $page->where('airports', ['EKBI', 'EKCH']));
    }

    public function test_an_airport_outside_the_chosen_firs_events_is_dropped(): void
    {
        $denmark = Fir::factory()->create(['icao' => 'EKDK']);
        Event::factory()->for($denmark)->create(['airports' => ['EKCH']]);
        Event::factory()->create(['airports' => ['ESSA']]);

        $this->get('/events?fir=EKDK&airport=ESSA')->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('filters.airport', null)
        );
    }

    public function test_the_front_page_shows_the_next_event_and_the_three_after_it(): void
    {
        foreach (['Fifth' => 5, 'Second' => 2, 'Fourth' => 4, 'First' => 1, 'Third' => 3] as $name => $days) {
            Event::factory()->create(['name' => $name, 'starts_at' => now()->addDays($days), 'ends_at' => now()->addDays($days)->addHours(3)]);
        }

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('upcomingEvents', fn ($events) => collect($events)->pluck('name')->all() === ['First', 'Second', 'Third', 'Fourth'])
        );
    }
}
