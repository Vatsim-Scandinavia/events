<?php

namespace Tests\Feature\Staffing;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DraftStaffingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 12:00', 'UTC'));
    }

    public function test_the_home_page_only_shows_published_events(): void
    {
        Event::factory()->draft()->create(['name' => 'Secret Fly-in']);
        Event::factory()->create(['name' => 'Public Fly-in']);

        foreach ([null, User::factory()->staff()->create(), User::factory()->admin()->create()] as $user) {
            if ($user) {
                $this->actingAs($user);
            }

            $this->get('/')->assertInertia(fn (Assert $page) => $page
                ->where('upcomingEvents', fn ($events) => collect($events)->pluck('name')->all() === ['Public Fly-in'])
            );
        }
    }

    public function test_shifts_on_a_draft_cannot_be_booked(): void
    {
        $draft = Event::factory()->draft()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-20 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 20:00', 'UTC'),
        ]);
        $occurrence = $draft->occurrences()->sole();
        $shift = $draft->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->actingAs(User::factory()->controller()->create())
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasErrors(['booking' => 'This event isn’t published yet.']);

        $this->assertDatabaseCount('shift_bookings', 0);
    }

    public function test_interest_in_a_draft_cannot_be_shown(): void
    {
        $draft = Event::factory()->draft()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 22:00', 'UTC'),
            'interest_opens_hours' => 14 * 24,
            'interest_closes_hours' => 48,
        ]);
        $occurrence = $draft->occurrences()->sole();
        $tower = $draft->positions()->create(['callsign' => 'ESSA_TWR']);

        $this->actingAs(User::factory()->controller()->create())->put("/occurrences/{$occurrence->id}/interest", [
            'positions' => [$tower->id],
            'windows' => [['from' => '18:00', 'until' => '21:00']],
        ])->assertSessionHasErrors(['interest' => 'This event isn’t published yet.']);

        $this->assertDatabaseCount('staffing_interests', 0);
    }

    public function test_the_roster_of_a_draft_cannot_be_published(): void
    {
        $draft = Event::factory()->draft()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 22:00', 'UTC'),
        ]);
        $occurrence = $draft->occurrences()->sole();

        $this->actingAs(User::factory()->staff()->create())
            ->post("/occurrences/{$occurrence->id}/roster/publish")
            ->assertSessionHasErrors(['roster' => 'Publish the event before its roster.']);

        $this->assertNull($occurrence->fresh()->roster_published_at);

        // The planner knows, so it doesn't offer to publish.
        $this->actingAs(User::factory()->staff()->create())->get("/events/{$draft->id}/staffing")->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.eventDraft', true)
        );
    }

    public function test_event_staff_can_still_set_up_staffing_on_a_draft(): void
    {
        $draft = Event::factory()->draft()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get("/events/{$draft->id}/staffing")->assertOk();
        $this->actingAs($staff)->put("/events/{$draft->id}/staffing", [
            'mode' => 'shifts',
            'interest_opens_hours' => 336,
            'interest_closes_hours' => 48,
        ])->assertSessionHasNoErrors();

        $this->assertSame(StaffingMode::Shifts, $draft->fresh()->staffing_mode);

        // The event page tells them controllers can't staff it yet.
        $this->actingAs($staff)->get("/events/{$draft->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.draft', true)
            ->where('staffing.mode', 'shifts')
        );
    }
}
