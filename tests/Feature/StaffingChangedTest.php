<?php

namespace Tests\Feature;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\User;
use App\Staffing\EventSchedule;
use App\Staffing\ShiftBookings;
use App\Staffing\StaffingChanged;
use App\Staffing\StaffingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * StaffingChanged is how Integrations follow staffing (ADR 0004), so every write that changes
 * who works which position when must announce the dates it touched, and a refused one nothing.
 */
class StaffingChangedTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventOccurrence $next;

    private EventOccurrence $later;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));

        // Two dates, a week apart: 18:00–22:00z on 10 and 17 November.
        $this->event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 22:00', 'UTC'),
        ]);
        $this->next = $this->event->occurrences()->sole();
        $this->later = $this->event->occurrences()->create(['starts_at' => $this->next->starts_at->addWeek(), 'ends_at' => $this->next->ends_at->addWeek()]);
        $this->event->unsetRelation('occurrences');

        Events::fake([StaffingChanged::class]);
    }

    public function test_booking_and_cancelling_a_shift_announce_its_date(): void
    {
        $controller = User::factory()->controller()->create();
        $shift = $this->shift();

        $booking = app(ShiftBookings::class)->book($controller, $shift, $this->next);
        $this->assertAnnounced([$this->next->id]);

        app(ShiftBookings::class)->cancel($controller, $booking);
        $this->assertAnnounced([$this->next->id], times: 2);
    }

    public function test_a_refused_booking_announces_nothing(): void
    {
        $shift = $this->shift();
        $this->travelTo($this->next->starts_at->addHour());

        try {
            app(ShiftBookings::class)->book(User::factory()->controller()->create(), $shift, $this->next->fresh());
            $this->fail('The booking was made.');
        } catch (ValidationException) {
            Events::assertNotDispatched(StaffingChanged::class);
        }
    }

    public function test_saving_the_staffing_plan_announces_every_date(): void
    {
        app(StaffingPlan::class)->save($this->event, $this->next, ['positions' => [['key' => -1, 'callsign' => 'EKCH_TWR']]]);

        $this->assertAnnounced([$this->next->id, $this->later->id]);
    }

    public function test_publishing_and_unpublishing_a_roster_announce_its_date(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::OpenInterest]);

        $this->next->fresh()->publishRoster();
        $this->assertAnnounced([$this->next->id]);

        $this->next->fresh()->unpublishRoster();
        $this->assertAnnounced([$this->next->id], times: 2);
    }

    public function test_cancelling_and_restoring_a_date_announce_that_date(): void
    {
        $this->next->cancel();
        $this->assertAnnounced([$this->next->id]);

        $this->next->fresh()->restore();
        $this->assertAnnounced([$this->next->id], times: 2);
    }

    public function test_cancelling_and_restoring_the_event_announce_every_date(): void
    {
        $this->event->cancel();
        $this->assertAnnounced([$this->next->id, $this->later->id]);

        $this->event->restore();
        $this->assertAnnounced([$this->next->id, $this->later->id], times: 2);
    }

    public function test_switching_staffing_mode_announces_every_date(): void
    {
        $this->event->switchStaffingMode(StaffingMode::OpenInterest);

        $this->assertAnnounced([$this->next->id, $this->later->id]);
    }

    public function test_saving_the_schedule_announces_dates_kept_removed_and_added(): void
    {
        app(EventSchedule::class)->save($this->event, collect([
            ['id' => $this->next->id, 'starts_at' => $this->next->starts_at->addHour(), 'ends_at' => $this->next->ends_at->addHour()],
            ['id' => null, 'starts_at' => $this->next->starts_at->addWeeks(2), 'ends_at' => $this->next->ends_at->addWeeks(2)],
        ]));

        $added = $this->event->occurrences()->whereKeyNot($this->next->id)->sole();

        $this->assertModelMissing($this->later);
        $this->assertAnnounced([$this->next->id, $this->later->id, $added->id]);
    }

    public function test_publishing_an_event_announces_every_date(): void
    {
        $this->event->forceFill(['published_at' => null])->save();

        $this->event->publish();

        $this->assertAnnounced([$this->next->id, $this->later->id]);
    }

    public function test_deleting_an_event_announces_the_dates_it_had(): void
    {
        $this->event->remove();

        $this->assertModelMissing($this->event);
        $this->assertAnnounced([$this->next->id, $this->later->id]);
    }

    private function shift(): PositionShift
    {
        return $this->event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
    }

    /**
     * The dates announced so far, across every announcement, are exactly these, in that many
     * announcements.
     *
     * @param  list<int>  $occurrenceIds
     */
    private function assertAnnounced(array $occurrenceIds, int $times = 1): void
    {
        $announced = Events::dispatched(StaffingChanged::class)->map(fn (array $arguments) => $arguments[0]->occurrenceIds);

        $this->assertCount($times, $announced);
        $this->assertEqualsCanonicalizing($occurrenceIds, $announced->flatten()->unique()->values()->all());
    }
}
