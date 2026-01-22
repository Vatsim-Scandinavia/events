<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancelledOccurrenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'moderator']);

        // Fake HTTP and Queue
        Http::fake([
            '*/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        Queue::fake();
    }

    public function test_admin_can_cancel_occurrence()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addWeek(),
            'end_datetime' => now()->addWeek()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=5',
        ]);

        $occurrenceDate = now()->addWeek()->toIso8601String();

        $response = $this->actingAs($admin)->post("/events/{$event->id}/cancel-occurrence", [
            'occurrence_date' => $occurrenceDate,
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertTrue($event->isOccurrenceCancelled($occurrenceDate));
    }

    public function test_admin_can_uncancel_occurrence()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addWeek(),
            'end_datetime' => now()->addWeek()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=5',
        ]);

        $occurrenceDate = now()->addWeek()->toIso8601String();
        $event->cancelOccurrence($occurrenceDate);

        $response = $this->actingAs($admin)->post("/events/{$event->id}/uncancel-occurrence", [
            'occurrence_date' => $occurrenceDate,
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertFalse($event->isOccurrenceCancelled($occurrenceDate));
    }

    public function test_cancelled_occurrences_are_filtered()
    {
        $calendar = Calendar::factory()->create();
        $startDate = Carbon::parse('2026-02-01 10:00:00');
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => $startDate,
            'end_datetime' => $startDate->copy()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=4',
        ]);

        // Cancel the second occurrence
        $secondOccurrence = $startDate->copy()->addWeek()->toIso8601String();
        $event->cancelOccurrence($secondOccurrence);

        $service = app(RecurringEventService::class);
        $instances = $service->generateInstances(
            $event->recurrence_rule,
            $event->start_datetime,
            $startDate->copy()->addMonths(2),
            10,
            $event->cancelled_occurrences ?? []
        );

        // Should have 3 instances (4 total - 1 cancelled)
        $this->assertCount(3, $instances);

        // Ensure the cancelled occurrence is not in the list
        foreach ($instances as $instance) {
            $this->assertNotEquals($secondOccurrence, $instance['start']->toIso8601String());
        }
    }

    public function test_generate_all_instances_includes_cancelled()
    {
        $calendar = Calendar::factory()->create();
        $startDate = Carbon::parse('2026-02-01 10:00:00');
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => $startDate,
            'end_datetime' => $startDate->copy()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=4',
        ]);

        // Cancel the second occurrence
        $secondOccurrence = $startDate->copy()->addWeek()->toIso8601String();
        $event->cancelOccurrence($secondOccurrence);

        $service = app(RecurringEventService::class);
        $instances = $service->generateAllInstances(
            $event->recurrence_rule,
            $event->start_datetime,
            $startDate->copy()->addMonths(2),
            10,
            $event->cancelled_occurrences ?? []
        );

        // Should have 4 instances (including cancelled)
        $this->assertCount(4, $instances);

        // Find the cancelled occurrence and verify it's marked
        $cancelledInstance = collect($instances)->firstWhere(function ($instance) use ($secondOccurrence) {
            return $instance['start']->toIso8601String() === $secondOccurrence;
        });

        $this->assertNotNull($cancelledInstance);
        $this->assertTrue($cancelledInstance['cancelled']);
    }

    public function test_cannot_cancel_occurrence_of_non_recurring_event()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'recurrence_rule' => null, // Non-recurring
        ]);

        $occurrenceDate = now()->addWeek()->toIso8601String();

        $response = $this->actingAs($admin)->post("/events/{$event->id}/cancel-occurrence", [
            'occurrence_date' => $occurrenceDate,
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_admin_can_access_manage_occurrences_page()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addWeek(),
            'end_datetime' => now()->addWeek()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=5',
        ]);

        $response = $this->actingAs($admin)->get("/events/{$event->id}/occurrences");

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Events/ManageOccurrences')
                ->has('event')
                ->has('occurrences')
        );
    }

    public function test_cannot_access_manage_occurrences_for_non_recurring_event()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'recurrence_rule' => null, // Non-recurring
        ]);

        $response = $this->actingAs($admin)->get("/events/{$event->id}/occurrences");

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_cancelled_occurrences_not_shown_on_home_page()
    {
        $calendar = Calendar::factory()->create(['is_public' => true]);
        $startDate = Carbon::parse('2026-02-01 10:00:00');
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => $startDate,
            'end_datetime' => $startDate->copy()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=4',
        ]);

        // Cancel the first occurrence (which would be the next one)
        $firstOccurrence = $startDate->toIso8601String();
        $event->cancelOccurrence($firstOccurrence);

        $response = $this->get('/');

        $response->assertStatus(200);

        // The calendar events should not include the cancelled occurrence
        $response->assertInertia(
            fn($page) => $page
                ->component('Home')
                ->has('calendarEvents')
                ->where('calendarEvents', function ($calendarEvents) use ($event, $firstOccurrence) {
                    // Ensure no calendar event matches the cancelled occurrence
                    foreach ($calendarEvents as $calEvent) {
                        if (str_starts_with($calEvent['id'], $event->id . '-')) {
                            $eventStart = Carbon::parse($calEvent['start']);
                            if ($eventStart->toIso8601String() === $firstOccurrence) {
                                return false; // Cancelled occurrence found - test should fail
                            }
                        }
                    }
                    return true; // Cancelled occurrence not found - good!
                })
        );
    }
}
