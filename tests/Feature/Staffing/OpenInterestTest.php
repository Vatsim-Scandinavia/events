<?php

namespace Tests\Feature\Staffing;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\StaffingInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpenInterestTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventOccurrence $occurrence;

    private EventPosition $tower;

    private EventPosition $approach;

    protected function setUp(): void
    {
        parent::setUp();

        // 18:00–22:00z on 20 November; interest opens 6 Nov 18:00z and closes 18 Nov 18:00z.
        $this->event = Event::factory()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 22:00', 'UTC'),
            'interest_opens_hours' => 14 * 24,
            'interest_closes_hours' => 48,
        ]);
        $this->occurrence = $this->event->occurrences()->sole();
        $this->tower = $this->event->positions()->create(['callsign' => 'ESSA_TWR']);
        $this->approach = $this->event->positions()->create(['callsign' => 'ESSA_APP']);

        $this->travelTo(Carbon::parse('2026-11-10 12:00', 'UTC'));
    }

    private function showInterest(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->put("/occurrences/{$this->occurrence->id}/interest", [
            'positions' => [$this->tower->id, $this->approach->id],
            'windows' => [['from' => '18:00', 'until' => '21:00']],
            'notes' => 'Happy to do either.',
            ...$overrides,
        ]);
    }

    public function test_a_controller_can_show_interest_with_their_availability(): void
    {
        $controller = User::factory()->controller()->create();

        $this->showInterest($controller)->assertSessionHasNoErrors();

        $interest = StaffingInterest::sole();
        $this->assertSame($controller->id, $interest->user_id);
        $this->assertEquals([$this->tower->id, $this->approach->id], $interest->positions->pluck('id')->all());
        $this->assertSame([['2026-11-20 18:00', '2026-11-20 21:00']], $this->windowsOf($interest));
    }

    public function test_a_controller_can_be_available_in_several_windows(): void
    {
        $this->showInterest(User::factory()->controller()->create(), [
            'windows' => [['from' => '20:30', 'until' => '22:00'], ['from' => '18:00', 'until' => '19:00']],
        ])->assertSessionHasNoErrors();

        // Stored in time order, whatever order they were entered in.
        $this->assertSame(
            [['2026-11-20 18:00', '2026-11-20 19:00'], ['2026-11-20 20:30', '2026-11-20 22:00']],
            $this->windowsOf(StaffingInterest::sole()),
        );
    }

    public function test_availability_windows_cannot_overlap(): void
    {
        $this->showInterest(User::factory()->controller()->create(), [
            'windows' => [['from' => '18:00', 'until' => '19:30'], ['from' => '19:00', 'until' => '21:00']],
        ])->assertSessionHasErrors('windows.1');

        $this->assertSame(0, StaffingInterest::count());
    }

    public function test_a_controller_without_windows_is_available_for_the_whole_event(): void
    {
        $this->showInterest(User::factory()->controller()->create(), ['windows' => []])->assertSessionHasNoErrors();

        $this->assertSame([['2026-11-20 18:00', '2026-11-20 22:00']], $this->windowsOf(StaffingInterest::sole()));
    }

    public function test_availability_is_read_in_the_controllers_own_time_zone(): void
    {
        // CET is UTC+1 in November, so the event runs 19:00–23:00 local.
        $controller = User::factory()->controller()->create(['timezone' => 'Europe/Copenhagen']);

        $this->showInterest($controller, ['windows' => [['from' => '19:00', 'until' => '22:00']]])->assertSessionHasNoErrors();

        $this->assertSame([['2026-11-20 18:00', '2026-11-20 21:00']], $this->windowsOf(StaffingInterest::sole()));
        $this->actingAs($controller)->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page->where('staffing.interest.mine.windows.0.from', '19:00'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function windowsOf(StaffingInterest $interest): array
    {
        return $interest->windows()->get()
            ->map(fn ($window) => [$window->starts_at->format('Y-m-d H:i'), $window->ends_at->format('Y-m-d H:i')])
            ->all();
    }

    public function test_showing_interest_again_replaces_the_earlier_one(): void
    {
        $controller = User::factory()->controller()->create();

        $this->showInterest($controller)->assertSessionHasNoErrors();
        $this->showInterest($controller, ['positions' => [$this->approach->id], 'windows' => [['from' => '19:00', 'until' => '21:00']]])->assertSessionHasNoErrors();

        $interest = StaffingInterest::sole();
        $this->assertEquals([$this->approach->id], $interest->positions->pluck('id')->all());
        $this->assertSame([['2026-11-20 19:00', '2026-11-20 21:00']], $this->windowsOf($interest));
    }

    public function test_availability_that_crosses_midnight_is_read_correctly(): void
    {
        $this->event->update(['ends_at' => Carbon::parse('2026-11-21 01:00', 'UTC')]);
        $this->occurrence->update(['ends_at' => Carbon::parse('2026-11-21 01:00', 'UTC')]);

        $this->showInterest(User::factory()->controller()->create(), ['windows' => [['from' => '22:00', 'until' => '00:30']]])
            ->assertSessionHasNoErrors();

        $this->assertSame([['2026-11-20 22:00', '2026-11-21 00:30']], $this->windowsOf(StaffingInterest::sole()));
    }

    public function test_availability_must_fall_within_the_event(): void
    {
        $this->showInterest(User::factory()->controller()->create(), ['windows' => [['from' => '18:00', 'until' => '23:00']]])
            ->assertSessionHasErrors('windows.0');
    }

    public function test_positions_must_belong_to_the_event(): void
    {
        $elsewhere = Event::factory()->create()->positions()->create(['callsign' => 'ENGM_TWR']);

        $this->showInterest(User::factory()->controller()->create(), ['positions' => [$elsewhere->id]])
            ->assertSessionHasErrors('positions');
    }

    public function test_interest_cannot_be_shown_before_the_window_opens_or_after_it_closes(): void
    {
        $controller = User::factory()->controller()->create();

        $this->travelTo(Carbon::parse('2026-11-06 17:59', 'UTC'));
        $this->showInterest($controller)->assertSessionHasErrors('interest');

        $this->travelTo(Carbon::parse('2026-11-18 18:00', 'UTC'));
        $this->showInterest($controller)->assertSessionHasErrors('interest');

        $this->assertSame(0, StaffingInterest::count());
    }

    public function test_observers_cannot_show_interest(): void
    {
        $this->showInterest(User::factory()->create())->assertForbidden();
    }

    public function test_a_controller_can_withdraw_while_the_window_is_open(): void
    {
        $controller = User::factory()->controller()->create();
        $this->showInterest($controller);

        $this->actingAs($controller)->delete("/occurrences/{$this->occurrence->id}/interest")->assertSessionHasNoErrors();

        $this->assertSame(0, StaffingInterest::count());
    }

    public function test_interest_is_only_visible_to_the_controller_and_event_staff(): void
    {
        $controller = User::factory()->controller()->create(['name' => 'Interested Controller']);
        $this->showInterest($controller);

        // The controller sees their own interest on the event page.
        $this->actingAs($controller)->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('staffing.interest.state', 'open')
                ->where('staffing.interest.mine.windows.0.from', '18:00')
                ->where('staffing.interest.mine.callsigns', ['ESSA_TWR', 'ESSA_APP'])
            );

        // Another controller and a guest see nothing of it.
        $this->actingAs(User::factory()->controller()->create())->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page->where('staffing.interest.mine', null)->where('staffing.interest.all', null));
        $this->actingAsGuest();
        $guestPage = $this->get("/events/{$this->event->id}");
        $guestPage->assertInertia(fn (Assert $page) => $page->where('auth.user', null)->where('staffing.interest.mine', null));
        $this->assertStringNotContainsString('Interested Controller', $guestPage->getContent());

        // Other controllers can't open the staff page that lists it.
        $this->actingAs(User::factory()->controller()->create())->get("/events/{$this->event->id}/staffing")->assertForbidden();

        // Event Staff see everyone's interest.
        $this->actingAs(User::factory()->staff()->create())->get("/events/{$this->event->id}/staffing")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Staffing')
                ->has('interests', 1)
                ->where('interests.0.controller.name', 'Interested Controller')
                ->where('interests.0.notes', 'Happy to do either.')
            );

        // vACC Staff see everyone's interest on the event page, without the staff page.
        $vaccStaff = User::factory()->vaccStaff()->create();
        $this->actingAs($vaccStaff)->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('staffing.interest.all', 1)
                ->where('staffing.interest.all.0.controller.name', 'Interested Controller')
                ->where('staffing.interest.all.0.callsigns', ['ESSA_TWR', 'ESSA_APP'])
            );
        $this->actingAs($vaccStaff)->get("/events/{$this->event->id}/staffing")->assertForbidden();
    }
}
