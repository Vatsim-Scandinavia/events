<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class OccurrencesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_dates_that_havent_ended_soonest_first(): void
    {
        $weekly = Event::factory()->create(['name' => 'Weekly', 'starts_at' => now()->subDays(7), 'ends_at' => now()->subDays(7)->addHours(2)]);
        $weekly->occurrences()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
        $weekly->occurrences()->create(['starts_at' => now()->addDays(9), 'ends_at' => now()->addDays(9)->addHours(2)]);
        Event::factory()->create(['name' => 'One-off', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2)]);
        Event::factory()->create(['name' => 'In progress', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        Event::factory()->draft()->create(['name' => 'Draft']);

        $this->getJson('/api/v1/occurrences')
            ->assertOk()
            ->assertJsonPath('data.*.event.name', ['In progress', 'Weekly', 'One-off', 'Weekly']);
    }

    public function test_a_date_is_listed_with_its_staffing_mode_and_event(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));
        $event = Event::factory()
            ->for(Fir::factory()->state(['icao' => 'EKDK', 'name' => 'Copenhagen']))
            ->withOpenInterest()
            ->create([
                'name' => 'Copenhagen Calling',
                'starts_at' => Carbon::parse('2026-07-18 18:00', 'UTC'),
                'ends_at' => Carbon::parse('2026-07-18 21:00', 'UTC'),
            ]);
        $occurrence = $event->occurrences->first();

        $this->getJson('/api/v1/occurrences')
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('links')
                ->has('meta')
                ->has('data.0', fn (AssertableJson $json) => $json
                    ->where('id', $occurrence->id)
                    ->where('startsAt', '2026-07-18T18:00:00+00:00')
                    ->where('endsAt', '2026-07-18T21:00:00+00:00')
                    ->where('cancelledAt', null)
                    ->where('rosterPublished', false)
                    ->where('staffingMode', 'open_interest')
                    ->has('event', fn (AssertableJson $json) => $json
                        ->where('id', $event->id)
                        ->where('name', 'Copenhagen Calling')
                        ->where('fir', ['icao' => 'EKDK', 'name' => 'Copenhagen'])
                        // The date it's embedded in stands for its dates.
                        ->missing('occurrences')
                        ->missing('description')
                        ->etc()
                    )
                )
            );
    }

    public function test_cancelled_dates_stay_listed_with_when_they_were_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));
        $cancelledEvent = Event::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
        $cancelledEvent->cancel();
        $withCancelledDate = Event::factory()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
        $withCancelledDate->occurrences->first()->cancel();

        $this->getJson('/api/v1/occurrences')
            ->assertJsonPath('data.0.cancelledAt', '2026-07-01T12:00:00+00:00')
            ->assertJsonPath('data.0.event.cancelledAt', '2026-07-01T12:00:00+00:00')
            ->assertJsonPath('data.1.cancelledAt', '2026-07-01T12:00:00+00:00')
            ->assertJsonPath('data.1.event.cancelledAt', null);
    }

    public function test_dates_can_be_narrowed_by_fir_type_and_range(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00', 'UTC'));
        $copenhagen = Fir::factory()->create(['icao' => 'EKDK']);
        $fno = EventType::factory()->create(['name' => 'Friday Night Ops']);
        $weekly = Event::factory()->for($copenhagen)->for($fno)->create(['name' => 'Copenhagen FNO', 'starts_at' => '2026-06-05 18:00', 'ends_at' => '2026-06-05 21:00']);
        $weekly->occurrences()->create(['starts_at' => '2026-06-12 18:00', 'ends_at' => '2026-06-12 21:00']);
        $weekly->occurrences()->create(['starts_at' => '2026-07-17 18:00', 'ends_at' => '2026-07-17 21:00']);
        Event::factory()->for($copenhagen)->create(['name' => 'Copenhagen other', 'starts_at' => '2026-06-10 18:00', 'ends_at' => '2026-06-10 21:00']);
        Event::factory()->for($fno)->create(['name' => 'Elsewhere FNO', 'starts_at' => '2026-06-06 18:00', 'ends_at' => '2026-06-06 21:00']);

        $this->getJson('/api/v1/occurrences?fir=EKDK&type=Friday Night Ops')
            ->assertJsonPath('data.*.startsAt', ['2026-07-17T18:00:00+00:00']);

        $this->getJson('/api/v1/occurrences?fir=EKDK&from=2026-06-01&to=2026-06-30')
            ->assertJsonPath('data.*.startsAt', ['2026-06-05T18:00:00+00:00', '2026-06-10T18:00:00+00:00', '2026-06-12T18:00:00+00:00']);

        $this->getJson('/api/v1/occurrences?to=2026-07-01&from=2026-07-02')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_dates_are_paginated(): void
    {
        $event = Event::factory()->create();
        foreach (range(1, 11) as $week) {
            $event->occurrences()->create(['starts_at' => now()->addWeeks($week), 'ends_at' => now()->addWeeks($week)->addHours(2)]);
        }

        $this->getJson('/api/v1/occurrences?per_page=5&page=3')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_a_date_is_shown_with_its_event(): void
    {
        $event = Event::factory()->withShifts()->past()->create();
        $occurrence = $event->occurrences->first();

        // Past dates can still be looked up.
        $this->getJson("/api/v1/occurrences/{$occurrence->id}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertJsonPath('data.id', $occurrence->id)
            ->assertJsonPath('data.staffingMode', 'shifts')
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonMissingPath('data.event.occurrences');
    }

    public function test_a_drafts_date_is_not_found(): void
    {
        $occurrence = Event::factory()->draft()->create()->occurrences->first();

        $this->getJson("/api/v1/occurrences/{$occurrence->id}")->assertNotFound();
        $this->getJson('/api/v1/occurrences/999999')->assertNotFound();
    }
}
