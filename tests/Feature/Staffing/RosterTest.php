<?php

namespace Tests\Feature\Staffing;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\RosterEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventOccurrence $occurrence;

    private EventPosition $tower;

    private EventPosition $approach;

    private User $staff;

    private User $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-19 12:00', 'UTC'));

        $this->event = Event::factory()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 22:00', 'UTC'),
        ]);
        $this->occurrence = $this->event->occurrences()->sole();
        $this->tower = $this->event->positions()->create(['callsign' => 'ESSA_TWR']);
        $this->approach = $this->event->positions()->create(['callsign' => 'ESSA_APP']);
        $this->staff = User::factory()->staff()->create();
        $this->controller = User::factory()->controller()->create(['name' => 'Rostered Controller']);
    }

    /**
     * Save the roster as the staff page does. Each slot is [controller, position, start, end, id?],
     * with times in minutes from the 18:00z start.
     *
     * @param  list<array{0: User, 1: EventPosition, 2: int, 3: int, 4?: int}>  $slots
     */
    private function saveRoster(array $slots, bool $publish = false, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->put("/events/{$this->event->id}/staffing/plan", [
            'occurrence_id' => $this->occurrence->id,
            'positions' => [
                ['key' => $this->tower->id, 'callsign' => 'ESSA_TWR'],
                ['key' => $this->approach->id, 'callsign' => 'ESSA_APP'],
            ],
            'roster' => array_map(fn (array $slot) => [
                'id' => $slot[4] ?? null,
                'position' => $slot[1]->id,
                'user_id' => $slot[0]->id,
                'start' => $slot[2],
                'end' => $slot[3],
            ], $slots),
            'publish' => $publish,
        ]);
    }

    public function test_staff_can_roster_a_controller_onto_a_position(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 0, 120]])->assertSessionHasNoErrors();

        $entry = RosterEntry::sole();
        $this->assertSame($this->tower->id, $entry->event_position_id);
        $this->assertSame('2026-11-20 20:00', $entry->ends_at->format('Y-m-d H:i'));
        $this->assertNull($this->occurrence->fresh()->roster_published_at);
    }

    public function test_a_position_cannot_be_rostered_twice_at_once(): void
    {
        $other = User::factory()->controller()->create();

        $this->saveRoster([[$this->controller, $this->tower, 0, 120], [$other, $this->tower, 60, 180]])->assertSessionHasErrors('plan');
        $this->saveRoster([[$this->controller, $this->tower, 0, 120], [$other, $this->tower, 120, 240]])->assertSessionHasNoErrors();
    }

    public function test_a_controller_cannot_be_rostered_on_two_positions_at_once(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 0, 120], [$this->controller, $this->approach, 90, 180]])
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, RosterEntry::count());
    }

    public function test_roster_slots_must_fall_within_the_event(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 180, 300]])->assertSessionHasErrors('plan');
    }

    public function test_only_rated_controllers_can_be_rostered(): void
    {
        $this->saveRoster([[User::factory()->create(), $this->tower, 0, 120]])->assertSessionHasErrors('plan');
    }

    public function test_only_staff_can_edit_the_roster(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 0, 120]], as: $this->controller)->assertForbidden();
        $this->actingAs($this->controller)->post("/occurrences/{$this->occurrence->id}/roster/publish")->assertForbidden();
    }

    public function test_the_roster_is_hidden_from_the_public_until_published(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 0, 120]]);

        $this->actingAs($this->controller)->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('staffing.rosterPublished', false)
                ->where('staffing.roster', null)
            );

        $this->actingAs($this->staff)->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page->has('staffing.roster', 1));

        // vACC Staff see it too, but can't change it.
        $this->actingAs(User::factory()->vaccStaff()->create())->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('staffing.roster', 1)
                ->where('staffing.canManage', false)
            );

        $this->saveRoster([[$this->controller, $this->tower, 0, 120, RosterEntry::sole()->id]], publish: true)->assertSessionHasNoErrors();
        $this->actingAsGuest();

        $this->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('staffing.rosterPublished', true)
                ->where('staffing.roster.0.callsign', 'ESSA_TWR')
                ->where('staffing.roster.0.controller.name', 'Rostered Controller')
                ->where('staffing.roster.0.time', '18:00–20:00z')
            );
    }

    public function test_staff_can_keep_editing_the_roster_during_the_event(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 0, 120]], publish: true);
        $entry = RosterEntry::sole();

        $this->travelTo(Carbon::parse('2026-11-20 19:00', 'UTC'));

        $this->saveRoster([[$this->controller, $this->approach, 0, 180, $entry->id]])->assertSessionHasNoErrors();

        $this->assertSame('21:00', $entry->fresh()->ends_at->format('H:i'));
        $this->assertSame($this->approach->id, $entry->fresh()->event_position_id);
        $this->assertNotNull($this->occurrence->fresh()->roster_published_at);
    }

    public function test_the_roster_is_archived_once_the_event_ends(): void
    {
        $this->saveRoster([[$this->controller, $this->tower, 0, 120]], publish: true);
        $entry = RosterEntry::sole();

        $this->travelTo(Carbon::parse('2026-11-20 22:00', 'UTC'));

        // Saving the plan leaves an archived roster alone.
        $this->saveRoster([])->assertSessionHasNoErrors();
        $this->assertModelExists($entry);

        $this->actingAs($this->staff)->delete("/occurrences/{$this->occurrence->id}/roster/publish")->assertSessionHasErrors('roster');

        // It stays on the event page as a record of who controlled.
        $this->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page->has('staffing.roster', 1));
    }
}
