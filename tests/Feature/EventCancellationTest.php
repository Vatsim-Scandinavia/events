<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\ShiftBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Event $event;

    private EventOccurrence $first;

    private EventOccurrence $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $this->staff = User::factory()->staff()->create();

        // 16:00–20:00z on 10 and 17 November.
        $this->event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $this->first = $this->event->occurrences()->sole();
        $this->second = $this->event->occurrences()->create([
            'starts_at' => Carbon::parse('2026-11-17 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-17 20:00', 'UTC'),
        ]);
    }

    private function shift(): PositionShift
    {
        return $this->event->positions()->create(['callsign' => 'EKCH_TWR', 'frequency' => '118.105'])
            ->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
    }

    public function test_only_staff_can_cancel(): void
    {
        $this->post("/events/{$this->event->id}/cancel")->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->controller()->create())->post("/events/{$this->event->id}/cancel")->assertForbidden();
        $this->actingAs(User::factory()->controller()->create())->post("/occurrences/{$this->first->id}/cancel")->assertForbidden();

        $this->assertNull($this->event->fresh()->cancelled_at);
    }

    public function test_a_cancelled_date_hands_staffing_to_the_next_date(): void
    {
        $this->actingAs($this->staff)->post("/occurrences/{$this->first->id}/cancel")->assertSessionHasNoErrors();

        $this->assertTrue($this->first->fresh()->isCancelled());
        $this->assertTrue($this->event->fresh()->currentOccurrence()->is($this->second));

        $this->get("/events/{$this->event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.occurrence.id', $this->second->id)
            ->where('staffing.occurrences.0.cancelled', true)
            ->where('event.cancelled', false)
        );

        // The event stays listed, by its next date that isn't cancelled.
        $this->get('/events')->assertInertia(fn (Assert $page) => $page->where('events.0.date', 'Tue 17 Nov'));
    }

    public function test_cancelled_dates_cannot_be_booked(): void
    {
        $this->event->update(['cancelled_at' => now()]);
        $shift = $this->shift();

        $this->actingAs(User::factory()->controller()->create())
            ->post("/occurrences/{$this->first->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasErrors('booking');

        $this->assertSame(0, ShiftBooking::count());
    }

    public function test_a_cancelled_event_leaves_the_list_but_keeps_its_page(): void
    {
        $this->actingAs($this->staff)->post("/events/{$this->event->id}/cancel")->assertSessionHasNoErrors();

        $this->get('/events')->assertInertia(fn (Assert $page) => $page->has('events', 0));
        $this->get("/events/{$this->event->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('event.cancelled', true)
            ->where('staffing.occurrence.cancelled', true)
        );
    }

    public function test_cancellations_can_be_restored_and_keep_their_staffing(): void
    {
        $controller = User::factory()->controller()->create();
        $shift = $this->shift();
        ShiftBooking::create(['position_shift_id' => $shift->id, 'event_occurrence_id' => $this->first->id, 'user_id' => $controller->id]);

        $this->actingAs($this->staff)->post("/occurrences/{$this->first->id}/cancel");
        $this->actingAs($this->staff)->post("/events/{$this->event->id}/cancel");

        // A date can't come back while the whole event is cancelled.
        $this->actingAs($this->staff)->delete("/occurrences/{$this->first->id}/cancel")->assertSessionHasErrors('cancel');

        $this->actingAs($this->staff)->delete("/events/{$this->event->id}/cancel")->assertSessionHasNoErrors();
        $this->assertFalse($this->second->fresh()->isCancelled());
        // The date cancelled on its own stays cancelled until it is restored too.
        $this->assertTrue($this->first->fresh()->isCancelled());

        $this->actingAs($this->staff)->delete("/occurrences/{$this->first->id}/cancel")->assertSessionHasNoErrors();
        $this->assertFalse($this->first->fresh()->isCancelled());
        $this->assertSame(1, $this->first->bookings()->count());
    }

    public function test_dates_that_have_started_cannot_be_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 17:00', 'UTC'));

        $this->actingAs($this->staff)->post("/occurrences/{$this->first->id}/cancel")->assertSessionHasErrors('cancel');
        $this->assertNull($this->first->fresh()->cancelled_at);
    }

    public function test_an_event_whose_dates_are_over_cannot_be_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 00:00', 'UTC'));

        $this->actingAs($this->staff)->post("/events/{$this->event->id}/cancel")->assertSessionHasErrors('cancel');
    }

    public function test_editing_the_schedule_does_not_move_a_cancelled_date(): void
    {
        $this->event->update(['repeats' => 'weekly', 'repeat_count' => 2]);
        $this->second->update(['cancelled_at' => now()]);

        // Moving the series a day later: the cancelled date is dropped, not moved.
        $this->actingAs($this->staff)->put("/events/{$this->event->id}", [
            'fir_id' => $this->event->fir_id,
            'name' => $this->event->name,
            'airports' => $this->event->airports,
            'starts_at' => '2026-11-11 16:00',
            'ends_at' => '2026-11-11 20:00',
            'repeats' => 'weekly',
            'repeat_ends' => 'count',
            'repeat_count' => 2,
        ])->assertSessionHasNoErrors();

        $dates = $this->event->occurrences()->get();

        $this->assertSame(['2026-11-11', '2026-11-18'], $dates->map(fn ($o) => $o->starts_at->toDateString())->all());
        $this->assertTrue($dates->every(fn (EventOccurrence $o) => $o->cancelled_at === null));
        $this->assertNull($this->second->fresh());
    }
}
