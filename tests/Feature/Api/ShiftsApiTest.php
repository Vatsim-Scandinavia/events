<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftsApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventOccurrence $first;

    private EventOccurrence $second;

    private PositionShift $towerEarly;

    private PositionShift $towerLate;

    private PositionShift $approach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-07-18 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-07-18 22:00', 'UTC'),
        ]);
        $this->first = $this->event->occurrences->first();
        $this->second = $this->event->occurrences()->create([
            'starts_at' => Carbon::parse('2026-07-25 17:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-07-25 21:00', 'UTC'),
        ]);

        $approach = $this->event->positions()->create(['callsign' => 'EKCH_APP', 'frequency' => '119.805', 'sort' => 2]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR', 'frequency' => '118.105', 'sort' => 1]);
        $this->towerLate = $tower->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);
        $this->towerEarly = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $this->approach = $approach->shifts()->create(['starts_offset' => 60, 'ends_offset' => 180]);
    }

    public function test_it_lists_every_shift_on_a_date_with_who_booked_it(): void
    {
        $controller = User::factory()->controller()->create(['vatsim_cid' => 1234567, 'name' => 'Anna Andersen']);
        $this->first->bookings()->create(['position_shift_id' => $this->towerEarly->id, 'user_id' => $controller->id]);

        $tower = ['id' => $this->towerEarly->event_position_id, 'callsign' => 'EKCH_TWR', 'frequency' => '118.105'];

        $this->getJson("/api/v1/occurrences/{$this->first->id}/shifts")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertExactJson(['data' => [
                [
                    'id' => $this->towerEarly->id,
                    'position' => $tower,
                    'startsAt' => '2026-07-18T18:00:00+00:00',
                    'endsAt' => '2026-07-18T20:00:00+00:00',
                    'controller' => ['cid' => 1234567, 'name' => 'Anna Andersen'],
                ],
                [
                    'id' => $this->towerLate->id,
                    'position' => $tower,
                    'startsAt' => '2026-07-18T20:00:00+00:00',
                    'endsAt' => '2026-07-18T22:00:00+00:00',
                    'controller' => null,
                ],
                [
                    'id' => $this->approach->id,
                    'position' => ['id' => $this->approach->event_position_id, 'callsign' => 'EKCH_APP', 'frequency' => '119.805'],
                    'startsAt' => '2026-07-18T19:00:00+00:00',
                    'endsAt' => '2026-07-18T21:00:00+00:00',
                    'controller' => null,
                ],
            ]]);
    }

    public function test_each_date_has_its_own_times_and_bookings(): void
    {
        $this->first->bookings()->create(['position_shift_id' => $this->towerEarly->id, 'user_id' => User::factory()->controller()->create()->id]);

        $this->getJson("/api/v1/occurrences/{$this->second->id}/shifts")
            ->assertJsonPath('data.0.id', $this->towerEarly->id)
            ->assertJsonPath('data.0.startsAt', '2026-07-25T17:00:00+00:00')
            ->assertJsonPath('data.0.endsAt', '2026-07-25T19:00:00+00:00')
            ->assertJsonPath('data.0.controller', null);
    }

    public function test_past_dates_are_served(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 12:00', 'UTC'));

        $this->getJson("/api/v1/occurrences/{$this->first->id}/shifts")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_event_not_staffed_through_shifts_has_none(): void
    {
        $event = Event::factory()->withOpenInterest()->create();
        $event->positions()->create(['callsign' => 'EKCH_TWR']);

        $this->getJson("/api/v1/occurrences/{$event->occurrences->first()->id}/shifts")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_a_drafts_date_is_not_found(): void
    {
        $this->event->forceFill(['published_at' => null])->save();

        $this->getJson("/api/v1/occurrences/{$this->first->id}/shifts")->assertNotFound();
        $this->getJson('/api/v1/occurrences/999999/shifts')->assertNotFound();
    }
}
