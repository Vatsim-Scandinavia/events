<?php

namespace Tests\Feature\Staffing;

use App\Enums\RosterState;
use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\User;
use App\Staffing\Roster;
use App\Staffing\RosterItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RosterVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-19 12:00', 'UTC'));
    }

    public function test_a_drafts_roster_shows_only_to_those_who_can_see_the_draft(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->draft()->withShifts());
        $occurrence->event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->assertSame(RosterState::Draft, Roster::of($occurrence)->state());
        $this->assertSame([], Roster::of($occurrence)->items()->all());
        $this->assertSame(RosterState::Draft, Roster::of($occurrence, User::factory()->controller()->create())->state());
        $this->assertSame(RosterState::Shown, Roster::of($occurrence, User::factory()->staff()->create())->state());
        $this->assertCount(1, Roster::of($occurrence, User::factory()->staff()->create())->items());
    }

    public function test_every_shift_shows_booked_or_open(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withShifts());
        $tower = $occurrence->event->positions()->create(['callsign' => 'ESSA_TWR', 'sort' => 1]);
        $early = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $late = $tower->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);
        $occurrence->event->positions()->create(['callsign' => 'ESSA_APP', 'sort' => 2]);
        $controller = User::factory()->controller()->create();
        $booking = $early->bookings()->create(['event_occurrence_id' => $occurrence->id, 'user_id' => $controller->id]);

        $roster = Roster::of($occurrence);

        $this->assertSame(RosterState::Shown, $roster->state());
        $this->assertSame([
            [$early->id, 'ESSA_TWR', '18:00', '20:00', $controller->id, $booking->id],
            [$late->id, 'ESSA_TWR', '20:00', '22:00', null, null],
        ], $this->summarise($roster));
        $this->assertTrue($roster->items()->every(fn (RosterItem $item) => $item->isShift()));
    }

    public function test_a_roster_from_interest_shows_to_staff_before_its_published_and_to_everyone_after(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withOpenInterest());
        $tower = $occurrence->event->positions()->create(['callsign' => 'ESSA_TWR']);
        $controller = User::factory()->controller()->create();
        $entry = $occurrence->rosterEntries()->create(['event_position_id' => $tower->id, 'user_id' => $controller->id, 'starts_at' => $occurrence->starts_at, 'ends_at' => $occurrence->ends_at]);

        $this->assertSame(RosterState::Unpublished, Roster::of($occurrence)->state());
        $this->assertSame([], Roster::of($occurrence)->items()->all());
        $this->assertSame(RosterState::Unpublished, Roster::of($occurrence, $controller)->state());
        $this->assertSame(RosterState::Shown, Roster::of($occurrence, User::factory()->staff()->create())->state());
        $this->assertSame(RosterState::Shown, Roster::of($occurrence, User::factory()->vaccStaff()->create())->state());

        $occurrence->update(['roster_published_at' => now()]);
        $roster = Roster::of($occurrence);

        $this->assertSame(RosterState::Shown, $roster->state());
        $this->assertSame([[$entry->id, 'ESSA_TWR', '18:00', '22:00', $controller->id, null]], $this->summarise($roster));
        $this->assertFalse($roster->items()->sole()->isShift());
        $this->assertSame(RosterState::Shown, Roster::of($occurrence, User::factory()->staff()->create())->state());
        $this->assertSame([[$entry->id, 'ESSA_TWR', '18:00', '22:00', $controller->id, null]], $this->summarise(Roster::of($occurrence, User::factory()->staff()->create())));
    }

    public function test_an_event_not_staffed_through_the_hub_has_no_roster(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->state(['staffing_mode' => StaffingMode::None]));
        $occurrence->event->positions()->create(['callsign' => 'ESSA_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->assertSame(RosterState::NotStaffed, Roster::of($occurrence, User::factory()->staff()->create())->state());
        $this->assertSame([], Roster::of($occurrence)->items()->all());
    }

    public function test_items_come_in_position_order_then_by_start_under_their_section(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withOpenInterest());
        $event = $occurrence->event;
        $arrivals = $event->sections()->create(['label' => 'Arrivals', 'sort' => 1]);
        $approach = $event->positions()->create(['callsign' => 'ESSA_APP', 'sort' => 2]);
        $departures = $event->sections()->create(['label' => 'Departures', 'sort' => 3]);
        $tower = $event->positions()->create(['callsign' => 'ESSA_TWR', 'sort' => 4]);
        $delivery = $event->positions()->create(['callsign' => 'ESSA_DEL', 'sort' => 0]);
        $controller = User::factory()->controller()->create();
        $entry = fn (EventPosition $position, int $from, int $until) => $occurrence->rosterEntries()->create([
            'event_position_id' => $position->id,
            'user_id' => $controller->id,
            'starts_at' => $occurrence->starts_at->addMinutes($from),
            'ends_at' => $occurrence->starts_at->addMinutes($until),
        ]);
        $entry($tower, 120, 240);
        $entry($tower, 0, 120);
        $entry($approach, 0, 240);
        $entry($delivery, 60, 120);
        $occurrence->update(['roster_published_at' => now()]);

        $items = Roster::of($occurrence)->items();

        $this->assertSame(
            [['ESSA_DEL', '19:00', null], ['ESSA_APP', '18:00', 'Arrivals'], ['ESSA_TWR', '18:00', 'Departures'], ['ESSA_TWR', '20:00', 'Departures']],
            $items->map(fn (RosterItem $item) => [$item->position->callsign, $item->startsAt->utc()->format('H:i'), $item->section?->label])->all(),
        );
        $this->assertTrue($items[1]->section->is($arrivals));
        $this->assertTrue($items[2]->section->is($departures));
    }

    /**
     * An Event with one date, 18:00–22:00z tomorrow.
     */
    private function occurrenceOf(Factory $factory): EventOccurrence
    {
        return $factory->create([
            'starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 22:00', 'UTC'),
        ])->occurrences()->sole();
    }

    /**
     * Each item as [shift or entry id, callsign, start, end, controller id, booking id].
     *
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: ?int, 5: ?int}>
     */
    private function summarise(Roster $roster): array
    {
        return $roster->items()->map(fn (RosterItem $item) => [
            $item->source->id,
            $item->position->callsign,
            $item->startsAt->utc()->format('H:i'),
            $item->endsAt->utc()->format('H:i'),
            $item->controller()?->id,
            $item->booking?->id,
        ])->all();
    }
}
