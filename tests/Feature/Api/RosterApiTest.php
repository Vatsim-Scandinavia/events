<?php

namespace Tests\Feature\Api;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RosterApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventOccurrence $occurrence;

    private EventPosition $tower;

    private User $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-01 12:00', 'UTC'));

        $this->event = Event::factory()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-07-18 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-07-18 22:00', 'UTC'),
        ]);
        $this->occurrence = $this->event->occurrences->first();
        $this->tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR', 'frequency' => '118.105']);
        $this->controller = User::factory()->controller()->create(['vatsim_cid' => 1234567, 'name' => 'Anna Andersen']);
    }

    private function roster(EventPosition $position, User $controller, string $startsAt, string $endsAt): void
    {
        $this->occurrence->rosterEntries()->create([
            'event_position_id' => $position->id,
            'user_id' => $controller->id,
            'starts_at' => Carbon::parse($startsAt, 'UTC'),
            'ends_at' => Carbon::parse($endsAt, 'UTC'),
        ]);
    }

    private function staffWith(StaffingMode $mode): void
    {
        $this->event->forceFill(['staffing_mode' => $mode])->save();
    }

    public function test_a_published_roster_is_listed_by_start(): void
    {
        $approach = $this->event->positions()->create(['callsign' => 'EKCH_APP', 'frequency' => '119.805']);
        $this->roster($this->tower, $this->controller, '2026-07-18 20:00', '2026-07-18 22:00');
        $this->roster($approach, User::factory()->controller()->create(['vatsim_cid' => 7654321, 'name' => 'Bo Berg']), '2026-07-18 18:00', '2026-07-18 20:00');
        $this->occurrence->publishRoster();

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.kind', 'rosterEntry')
            ->assertJsonPath('data.0.position', ['id' => $approach->id, 'callsign' => 'EKCH_APP', 'frequency' => '119.805'])
            ->assertJsonPath('data.0.startsAt', '2026-07-18T18:00:00+00:00')
            ->assertJsonPath('data.0.endsAt', '2026-07-18T20:00:00+00:00')
            ->assertJsonPath('data.0.controller', ['cid' => 7654321, 'name' => 'Bo Berg'])
            ->assertJsonPath('data.1.position.callsign', 'EKCH_TWR')
            ->assertJsonPath('data.1.controller', ['cid' => 1234567, 'name' => 'Anna Andersen']);
    }

    public function test_an_unpublished_roster_is_empty_until_its_published(): void
    {
        $this->roster($this->tower, $this->controller, '2026-07-18 18:00', '2026-07-18 20:00');

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertOk()->assertExactJson(['data' => []]);
        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}")->assertJsonPath('data.rosterPublished', false);

        $this->occurrence->publishRoster();

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}")->assertJsonPath('data.rosterPublished', true);
    }

    public function test_past_rosters_are_served(): void
    {
        $this->roster($this->tower, $this->controller, '2026-07-18 18:00', '2026-07-18 20:00');
        $this->occurrence->publishRoster();
        $this->travelTo(Carbon::parse('2026-08-01 12:00', 'UTC'));

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertJsonCount(1, 'data');
    }

    public function test_a_shifts_event_lists_every_shift_open_or_booked_without_being_published(): void
    {
        $this->staffWith(StaffingMode::Shifts);
        $approach = $this->event->positions()->create(['callsign' => 'EKCH_APP', 'frequency' => '119.805']);
        $towerLate = $this->tower->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);
        $towerEarly = $this->tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $approachShift = $approach->shifts()->create(['starts_offset' => 60, 'ends_offset' => 180]);
        $this->occurrence->bookings()->create(['position_shift_id' => $towerEarly->id, 'user_id' => $this->controller->id]);

        $tower = ['id' => $this->tower->id, 'callsign' => 'EKCH_TWR', 'frequency' => '118.105'];

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")
            ->assertOk()
            ->assertExactJson(['data' => [
                [
                    'kind' => 'shift',
                    'id' => $towerEarly->id,
                    'position' => $tower,
                    'startsAt' => '2026-07-18T18:00:00+00:00',
                    'endsAt' => '2026-07-18T20:00:00+00:00',
                    'controller' => ['cid' => 1234567, 'name' => 'Anna Andersen'],
                ],
                [
                    'kind' => 'shift',
                    'id' => $approachShift->id,
                    'position' => ['id' => $approach->id, 'callsign' => 'EKCH_APP', 'frequency' => '119.805'],
                    'startsAt' => '2026-07-18T19:00:00+00:00',
                    'endsAt' => '2026-07-18T21:00:00+00:00',
                    'controller' => null,
                ],
                [
                    'kind' => 'shift',
                    'id' => $towerLate->id,
                    'position' => $tower,
                    'startsAt' => '2026-07-18T20:00:00+00:00',
                    'endsAt' => '2026-07-18T22:00:00+00:00',
                    'controller' => null,
                ],
            ]]);
    }

    public function test_each_date_of_a_shifts_event_has_its_own_times_and_bookings(): void
    {
        $this->staffWith(StaffingMode::Shifts);
        $shift = $this->tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $this->occurrence->bookings()->create(['position_shift_id' => $shift->id, 'user_id' => $this->controller->id]);
        $second = $this->event->occurrences()->create([
            'starts_at' => Carbon::parse('2026-07-25 17:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-07-25 21:00', 'UTC'),
        ]);

        $this->getJson("/api/v1/occurrences/{$second->id}/roster")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shift->id)
            ->assertJsonPath('data.0.startsAt', '2026-07-25T17:00:00+00:00')
            ->assertJsonPath('data.0.endsAt', '2026-07-25T19:00:00+00:00')
            ->assertJsonPath('data.0.controller', null);
    }

    public function test_items_starting_together_are_ordered_by_callsign(): void
    {
        $this->staffWith(StaffingMode::Shifts);
        $ground = $this->event->positions()->create(['callsign' => 'EKCH_GND']);
        $this->tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $ground->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")
            ->assertJsonPath('data.0.position.callsign', 'EKCH_GND')
            ->assertJsonPath('data.1.position.callsign', 'EKCH_TWR');
    }

    public function test_only_the_current_staffing_mode_is_shown(): void
    {
        // A past date's roster is kept as a record when staffing switches, but it's no longer shown.
        $this->roster($this->tower, $this->controller, '2026-07-18 18:00', '2026-07-18 20:00');
        $this->occurrence->publishRoster();
        $this->staffWith(StaffingMode::Shifts);
        $this->tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'shift');
    }

    public function test_an_event_not_staffed_through_the_hub_has_no_roster(): void
    {
        $this->tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $this->roster($this->tower, $this->controller, '2026-07-18 18:00', '2026-07-18 20:00');
        $this->occurrence->publishRoster();
        $this->staffWith(StaffingMode::None);

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_a_drafts_date_is_not_found(): void
    {
        $this->event->forceFill(['published_at' => null])->save();

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertNotFound();
        $this->getJson('/api/v1/occurrences/999999/roster')->assertNotFound();
    }

    public function test_shifts_have_no_endpoint_of_their_own(): void
    {
        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/shifts")->assertNotFound();
    }
}
