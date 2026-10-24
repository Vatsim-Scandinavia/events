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

    public function test_an_event_not_staffed_through_a_roster_has_none(): void
    {
        $this->roster($this->tower, $this->controller, '2026-07-18 18:00', '2026-07-18 20:00');
        $this->occurrence->publishRoster();
        // A past date's roster is kept as a record when staffing switches, but it's no longer shown.
        $this->event->forceFill(['staffing_mode' => StaffingMode::Shifts])->save();

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_a_drafts_date_is_not_found(): void
    {
        $this->event->forceFill(['published_at' => null])->save();

        $this->getJson("/api/v1/occurrences/{$this->occurrence->id}/roster")->assertNotFound();
        $this->getJson('/api/v1/occurrences/999999/roster')->assertNotFound();
    }
}
