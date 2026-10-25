<?php

namespace Tests\Feature;

use App\Models\Event;
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

    public function test_the_front_page_shows_the_next_three_events(): void
    {
        Event::factory()->count(5)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('upcomingEvents', 3)
        );
    }
}
