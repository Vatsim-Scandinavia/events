<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_event_with_its_briefing_details(): void
    {
        $event = Event::factory()->create([
            'name' => 'Copenhagen Fly-in',
            'description' => 'Full coverage at Kastrup.',
            'starts_at' => now('UTC')->addDays(3)->setTime(16, 0),
            'ends_at' => now('UTC')->addDays(3)->setTime(19, 30),
            'routes' => [['from' => 'EKBI', 'to' => 'EKCH', 'route' => 'TUDLO']],
            'booking_url' => 'https://example.com/book',
        ]);
        $event->positions()->create(['callsign' => 'EKCH_TWR', 'frequency' => '118.105']);

        $this->get("/events/{$event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('event.name', 'Copenhagen Fly-in')
                ->where('event.description', "<p>Full coverage at Kastrup.</p>\n")
                ->where('event.time', '16:00–19:30z')
                ->where('event.duration', '3h 30m')
                ->where('event.hasEnded', false)
                ->where('event.routes.0.route', 'TUDLO')
                ->missing('event.positions')
                ->where('staffing.mode', 'none')
                ->missing('staffing.positions')
                ->where('event.bookingUrl', 'https://example.com/book')
                ->where('event.briefingUrl', null)
            );
    }

    public function test_the_description_is_rendered_from_markdown_without_anything_unsafe(): void
    {
        $event = Event::factory()->create([
            'description' => "Bring **charts**\nand fuel.\n\n- [Book](https://example.com) [Bad](javascript:alert(1))\n\n<script>alert(1)</script><img src=x onerror=alert(1)>\n\n![Airport map](https://example.com/map.png)",
        ]);

        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('event.description', function (string $html) {
                $this->assertStringContainsString("<p>Bring <strong>charts</strong><br>\nand fuel.</p>", $html);
                $this->assertStringContainsString('<a href="https://example.com">Book</a>', $html);
                $this->assertStringContainsString('Airport map', $html);
                $this->assertStringNotContainsString('javascript:', $html);
                $this->assertStringNotContainsString('<script', $html);
                $this->assertStringNotContainsString('<img', $html);
                $this->assertStringNotContainsString('onerror', $html);

                return true;
            })
        );
    }

    public function test_times_follow_the_viewers_time_zone_and_keep_zulu_on_hand(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));
        $event = Event::factory()->create([
            'starts_at' => Carbon::parse('2026-07-18 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-07-18 19:30', 'UTC'),
        ]);

        $this->actingAs(User::factory()->withTimezone('Europe/Copenhagen')->create())
            ->get("/events/{$event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.time', '18:00–21:30 CEST')
                ->where('event.zuluTime', '16:00–19:30z')
            );

        $this->actingAs(User::factory()->create())
            ->get("/events/{$event->id}")
            ->assertInertia(fn (Assert $page) => $page->where('event.time', '16:00–19:30z'));
    }

    public function test_finished_events_are_still_viewable(): void
    {
        $event = Event::factory()->past()->create();

        $this->get("/events/{$event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.hasEnded', true)
                ->where('event.routes', [])
                ->where('staffing.mode', 'none')
            );
    }

    public function test_an_unknown_event_returns_not_found(): void
    {
        $this->get('/events/999')->assertNotFound();
    }
}
