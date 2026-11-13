<?php

namespace Tests\Feature\Staffing;

use App\Enums\BookingRefusal;
use App\Enums\InterestRefusal;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Staffing\ShiftBookings;
use App\Staffing\StaffingInterests;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffingRefusalTest extends TestCase
{
    use RefreshDatabase;

    private User $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 12:00', 'UTC'));
        $this->controller = User::factory()->controller()->create();
    }

    public function test_a_controller_may_book_the_next_date_until_it_starts(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withShifts());

        $this->assertNull($this->bookingRefusal($this->controller, $occurrence));
    }

    public function test_booking_explains_the_date_before_the_person(): void
    {
        $draft = $this->occurrenceOf(Event::factory()->withShifts()->draft());
        $started = $this->occurrenceOf(Event::factory()->withShifts());

        $this->assertSame(BookingRefusal::Draft, $this->bookingRefusal(null, $draft));
        $this->assertSame(BookingRefusal::NotShifts, $this->bookingRefusal(null, $this->occurrenceOf(Event::factory()->withOpenInterest())));

        $this->travelTo(Carbon::parse('2026-11-20 18:30', 'UTC'));
        $this->assertSame(BookingRefusal::Started, $this->bookingRefusal(User::factory()->create(), $started->fresh()));
    }

    public function test_booking_is_refused_on_a_cancelled_date_and_any_but_the_next(): void
    {
        $cancelled = $this->occurrenceOf(Event::factory()->withShifts());
        $cancelled->update(['cancelled_at' => now()]);
        $next = $this->occurrenceOf(Event::factory()->withShifts());
        $later = $next->event->occurrences()->create(['starts_at' => $next->starts_at->addWeek(), 'ends_at' => $next->ends_at->addWeek()]);

        $this->assertSame(BookingRefusal::Cancelled, $this->bookingRefusal($this->controller, $cancelled->fresh()));
        $this->assertSame(BookingRefusal::NotNextDate, $this->bookingRefusal($this->controller, $later->fresh()));
        $this->assertNull($this->bookingRefusal($this->controller, $next->fresh()));
    }

    public function test_booking_needs_someone_signed_in_who_may_control(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withShifts());

        $this->assertSame(BookingRefusal::SignInNeeded, $this->bookingRefusal(null, $occurrence));
        $this->assertSame(BookingRefusal::NotController, $this->bookingRefusal(User::factory()->rated()->create(), $occurrence));
    }

    public function test_a_controller_may_show_interest_while_the_window_is_open(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withOpenInterest());

        $this->assertNull($this->interestRefusal($this->controller, $occurrence));
    }

    public function test_interest_explains_the_date_before_the_person(): void
    {
        $this->assertSame(InterestRefusal::Draft, $this->interestRefusal(null, $this->occurrenceOf(Event::factory()->withOpenInterest()->draft())));
        $this->assertSame(InterestRefusal::NotInterest, $this->interestRefusal(null, $this->occurrenceOf(Event::factory()->withShifts())));

        $occurrence = $this->occurrenceOf(Event::factory()->withOpenInterest());

        $this->travelTo(Carbon::parse('2026-11-06 12:00', 'UTC'));
        $this->assertSame(InterestRefusal::NotOpenYet, $this->interestRefusal(null, $occurrence->fresh()));

        $this->travelTo(Carbon::parse('2026-11-19 12:00', 'UTC'));
        $this->assertSame(InterestRefusal::Closed, $this->interestRefusal(null, $occurrence->fresh()));
    }

    public function test_interest_on_a_cancelled_date_is_refused_as_cancelled_even_once_the_window_closes(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withOpenInterest());
        $occurrence->update(['cancelled_at' => now()]);

        $this->assertSame(InterestRefusal::Cancelled, $this->interestRefusal($this->controller, $occurrence->fresh()));

        $this->travelTo(Carbon::parse('2026-11-19 12:00', 'UTC'));
        $this->assertSame(InterestRefusal::Cancelled, $this->interestRefusal($this->controller, $occurrence->fresh()));
    }

    public function test_interest_is_refused_for_any_but_the_next_date(): void
    {
        $next = $this->occurrenceOf(Event::factory()->withOpenInterest());
        $later = $next->event->occurrences()->create(['starts_at' => $next->starts_at->addDays(3), 'ends_at' => $next->ends_at->addDays(3)]);

        $this->assertSame(InterestRefusal::NotNextDate, $this->interestRefusal($this->controller, $later->fresh()));
    }

    public function test_interest_needs_someone_signed_in_who_may_control(): void
    {
        $occurrence = $this->occurrenceOf(Event::factory()->withOpenInterest());

        $this->assertSame(InterestRefusal::SignInNeeded, $this->interestRefusal(null, $occurrence));
        $this->assertSame(InterestRefusal::NotController, $this->interestRefusal(User::factory()->rated()->create(), $occurrence));
    }

    /**
     * An Event with one date, 18:00–22:00z on 20 November. Interest opens 14 days before and
     * closes 48 hours before, from 6 Nov 18:00z to 18 Nov 18:00z.
     */
    private function occurrenceOf(Factory $factory): EventOccurrence
    {
        return $factory->create([
            'starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-20 22:00', 'UTC'),
            'interest_opens_hours' => 14 * 24,
            'interest_closes_hours' => 48,
        ])->occurrences()->sole();
    }

    private function bookingRefusal(?User $user, EventOccurrence $occurrence): ?BookingRefusal
    {
        return app(ShiftBookings::class)->refusal($user, $occurrence);
    }

    private function interestRefusal(?User $user, EventOccurrence $occurrence): ?InterestRefusal
    {
        return app(StaffingInterests::class)->refusal($user, $occurrence);
    }
}
