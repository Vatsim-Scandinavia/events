<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

        Http::fake(['*/webhooks/*' => Http::response(['success' => true], 200)]);
        Queue::fake();
    }

    public function test_admin_can_cancel_occurrence()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addWeek()->startOfHour(),
            'end_datetime' => now()->addWeek()->startOfHour()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=5',
        ]);

        $occurrenceDate = $event->start_datetime->toISOString();

        $response = $this->actingAs($admin)->post("/events/{$event->id}/cancel-occurrence", [
            'occurrence_date' => $occurrenceDate,
        ]);

        $response->assertRedirect();
        $event->refresh();
        
        $this->assertContains($occurrenceDate, $event->cancelled_occurrences ?? []);
    }

    public function test_cancelled_occurrences_are_marked_in_service()
    {
        $calendar = Calendar::factory()->create();
        $startDate = Carbon::parse('2026-02-01 10:00:00');
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => $startDate,
            'end_datetime' => $startDate->copy()->addHours(2),
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=4',
            'cancelled_occurrences' => [$startDate->copy()->addWeek()->toISOString()]
        ]);

        $service = app(EventService::class);
        $instances = $service->generateUpcomingInstances($event, limit: 10);

        $this->assertCount(4, $instances);

        $secondOcc = $instances->firstWhere('start', $startDate->copy()->addWeek()->toISOString());
        $this->assertTrue($secondOcc['cancelled']);
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
            'cancelled_occurrences' => [$startDate->toISOString()]
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);

        $response->assertInertia(function ($page) use ($startDate) {
            $calendarEvents = $page->toArray()['props']['calendarEvents'];
            
            foreach ($calendarEvents as $calEvent) {
                if ($calEvent['start'] === $startDate->toISOString()) {
                    $this->fail('Cancelled occurrence appeared on the Home page calendar.');
                }
            }
            return true;
        });
    }
}