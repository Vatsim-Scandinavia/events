<?php

namespace Tests\Feature\Api;

use App\Enums\RepeatInterval;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class EventsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_published_upcoming_events_soonest_first(): void
    {
        Event::factory()->create(['name' => 'Later', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(3)]);
        Event::factory()->create(['name' => 'Sooner', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(3)]);
        Event::factory()->create(['name' => 'In progress', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        Event::factory()->past()->create(['name' => 'Finished']);
        Event::factory()->draft()->create(['name' => 'Draft']);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['In progress', 'Sooner', 'Later']);
    }

    public function test_a_repeating_event_is_ordered_by_its_next_date(): void
    {
        $weekly = Event::factory()->create(['name' => 'Weekly', 'starts_at' => now()->subDays(7), 'ends_at' => now()->subDays(7)->addHours(2)]);
        $weekly->occurrences()->create(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2)]);
        Event::factory()->create(['name' => 'One-off', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(2)]);

        $this->getJson('/api/v1/events')
            ->assertJsonPath('data.*.name', ['One-off', 'Weekly'])
            ->assertJsonCount(2, 'data.1.occurrences');
    }

    public function test_an_event_is_listed_with_its_fields_in_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));
        $event = Event::factory()
            ->for(Fir::factory()->state(['icao' => 'EKDK', 'name' => 'Copenhagen']))
            ->for(EventType::factory()->state(['name' => 'Friday Night Ops']))
            ->withShifts()
            ->create([
                'name' => 'Copenhagen Calling',
                'airports' => ['EKCH'],
                'routes' => [['from' => 'EKCH', 'to' => 'ESSA', 'route' => 'SIMEG']],
                'starts_at' => Carbon::parse('2026-07-18 18:00', 'UTC'),
                'ends_at' => Carbon::parse('2026-07-18 21:00', 'UTC'),
                'timezone' => 'Europe/Copenhagen',
                'repeats' => RepeatInterval::Weekly,
                'booking_url' => 'https://slots.example/ekch',
                'briefing_url' => 'https://briefing.example/ekch',
            ]);

        $this->getJson('/api/v1/events')
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('links')
                ->has('meta')
                ->has('data.0', fn (AssertableJson $json) => $json
                    ->where('id', $event->id)
                    ->where('name', 'Copenhagen Calling')
                    ->where('url', route('events.show', $event))
                    ->where('type', ['name' => 'Friday Night Ops'])
                    ->where('fir', ['icao' => 'EKDK', 'name' => 'Copenhagen'])
                    ->where('airports', ['EKCH'])
                    ->where('routes', [['from' => 'EKCH', 'to' => 'ESSA', 'route' => 'SIMEG']])
                    ->where('banner', null)
                    ->where('slotUrl', 'https://slots.example/ekch')
                    ->where('briefingUrl', 'https://briefing.example/ekch')
                    ->where('timezone', 'Europe/Copenhagen')
                    ->where('repeats', 'weekly')
                    ->where('staffingMode', 'shifts')
                    ->where('publishedAt', '2026-07-01T12:00:00+00:00')
                    ->where('cancelledAt', null)
                    ->has('occurrences', 1, fn (AssertableJson $json) => $json
                        ->where('id', $event->occurrences->first()->id)
                        ->where('startsAt', '2026-07-18T18:00:00+00:00')
                        ->where('endsAt', '2026-07-18T21:00:00+00:00')
                        ->where('cancelledAt', null)
                        ->where('rosterPublished', false)
                    )
                    // The list stays light: descriptions and positions are on the event itself.
                    ->missing('description')
                    ->missing('positions')
                )
            );
    }

    public function test_cancelled_events_and_dates_stay_listed_with_when_they_were_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));
        $cancelled = Event::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
        $cancelled->cancel();
        $withCancelledDate = Event::factory()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
        $withCancelledDate->occurrences->first()->cancel();

        $this->getJson('/api/v1/events')
            ->assertJsonPath('data.0.id', $cancelled->id)
            ->assertJsonPath('data.0.cancelledAt', '2026-07-01T12:00:00+00:00')
            ->assertJsonPath('data.0.occurrences.0.cancelledAt', '2026-07-01T12:00:00+00:00')
            ->assertJsonPath('data.1.id', $withCancelledDate->id)
            ->assertJsonPath('data.1.cancelledAt', null)
            ->assertJsonPath('data.1.occurrences.0.cancelledAt', '2026-07-01T12:00:00+00:00');
    }

    public function test_events_can_be_narrowed_by_fir_and_type(): void
    {
        $copenhagen = Fir::factory()->create(['icao' => 'EKDK']);
        $fno = EventType::factory()->create(['name' => 'Friday Night Ops']);
        Event::factory()->for($copenhagen)->for($fno)->create(['name' => 'Copenhagen FNO']);
        Event::factory()->for($copenhagen)->create(['name' => 'Copenhagen other']);
        Event::factory()->for($fno)->create(['name' => 'Elsewhere FNO']);

        $this->getJson('/api/v1/events?fir=EKDK')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.*.fir.icao', ['EKDK', 'EKDK']);

        $this->getJson('/api/v1/events?fir=EKDK&type=Friday Night Ops')
            ->assertJsonPath('data.*.name', ['Copenhagen FNO']);
    }

    public function test_a_date_range_reaches_past_events(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00', 'UTC'));
        Event::factory()->create(['name' => 'Early June', 'starts_at' => '2026-06-02 18:00', 'ends_at' => '2026-06-02 21:00']);
        Event::factory()->create(['name' => 'Mid June', 'starts_at' => '2026-06-15 23:00', 'ends_at' => '2026-06-16 01:00']);
        Event::factory()->create(['name' => 'Last of June', 'starts_at' => '2026-06-30 18:00', 'ends_at' => '2026-06-30 21:00']);
        Event::factory()->create(['name' => 'Next week', 'starts_at' => '2026-07-22 18:00', 'ends_at' => '2026-07-22 21:00']);

        $this->getJson('/api/v1/events?from=2026-06-16&to=2026-06-30')
            ->assertJsonPath('data.*.name', ['Mid June', 'Last of June']);

        $this->getJson('/api/v1/events?from=2026-06-20')
            ->assertJsonPath('data.*.name', ['Last of June', 'Next week']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/v1/events?from=yesterday')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/api/v1/events?from=2026-07-02&to=2026-07-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/events?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_events_are_paginated(): void
    {
        Event::factory()->count(30)->create();

        $this->getJson('/api/v1/events')
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 30);

        $this->getJson('/api/v1/events?per_page=10&page=3')
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('links.prev', fn (string $url) => str_contains($url, 'per_page=10'));
    }

    public function test_an_event_shows_its_description_and_positions(): void
    {
        $event = Event::factory()->withShifts()->create(['description' => "Come fly **Copenhagen**.\n\n<script>alert(1)</script>"]);
        $position = $event->positions()->create(['callsign' => 'EKCH_TWR', 'frequency' => '118.105']);
        $shift = $position->shifts()->create(['starts_offset' => 0, 'ends_offset' => 90]);

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.description', "Come fly **Copenhagen**.\n\n<script>alert(1)</script>")
            ->assertJsonPath('data.descriptionHtml', fn (string $html) => str_contains($html, '<strong>Copenhagen</strong>') && ! str_contains($html, '<script>'))
            ->assertJsonPath('data.positions', [[
                'id' => $position->id,
                'callsign' => 'EKCH_TWR',
                'frequency' => '118.105',
                'shifts' => [['id' => $shift->id, 'startOffset' => 0, 'endOffset' => 90]],
            ]]);
    }

    public function test_a_draft_is_not_found(): void
    {
        $draft = Event::factory()->draft()->create();

        $this->getJson("/api/v1/events/{$draft->id}")->assertNotFound();
        $this->getJson('/api/v1/events/999999')->assertNotFound();
    }

    public function test_responses_may_be_cached_for_a_minute(): void
    {
        $event = Event::factory()->create();

        $this->getJson('/api/v1/events')->assertHeader('Cache-Control', 'max-age=60, public');
        $this->getJson("/api/v1/events/{$event->id}")->assertHeader('Cache-Control', 'max-age=60, public');
    }

    public function test_requests_are_limited_per_minute_per_address(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/events')->assertOk();
        }

        $this->getJson('/api/v1/events')->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/v1/events')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->travel(1)->minute();
        $this->getJson('/api/v1/events')->assertOk();
    }

    public function test_the_api_docs_are_public(): void
    {
        $this->get('/docs/api')->assertOk();
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('paths./events')->has('paths./events/{event}')->has('paths./occurrences')->has('paths./occurrences/{occurrence}')->has('paths./occurrences/{occurrence}/shifts')->etc());
    }
}
