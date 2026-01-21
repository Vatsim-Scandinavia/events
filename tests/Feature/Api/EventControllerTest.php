<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\Event;
use App\Models\Calendar;
use App\Models\Staffing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class EventControllerTest extends TestCase
{
    use RefreshDatabase, WithApiAuthentication;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
        ]);
        $this->setUpApiKeys();
    }

    #[Test]
    public function it_can_list_all_events()
    {
        $calendar = Calendar::factory()->create();
        Event::factory()->count(3)->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_upcoming_events_by_default()
    {
        $calendar = Calendar::factory()->create();
        
        // Past event
        Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->subDays(7),
            'end_datetime' => now()->subDays(7)->addHours(3),
        ]);

        // Future events
        Event::factory()->count(2)->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_include_past_events()
    {
        $calendar = Calendar::factory()->create();
        
        Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->subDays(7),
            'end_datetime' => now()->subDays(7)->addHours(3),
        ]);

        Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);

        $response = $this->getJson('/api/v1/events?upcoming=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_filter_events_with_staffing()
    {
        $calendar = Calendar::factory()->create();
        
        // Event with staffing
        $eventWithStaffing = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);
        Staffing::factory()->create(['event_id' => $eventWithStaffing->id]);

        // Event without staffing
        Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);

        $response = $this->getJson('/api/v1/events?staffing=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_can_get_single_event()
    {
        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'title' => 'Test Event',
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('event_id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertEquals('Test Event', $data['title']);
    }

    #[Test]
    public function it_returns_404_for_non_existent_event()
    {
        $response = $this->getJson('/api/v1/events/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_includes_url_and_banner_fields()
    {
        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('banner', $data);
    }

    #[Test]
    public function it_orders_events_by_start_datetime()
    {
        $calendar = Calendar::factory()->create();
        
        $event1 = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(10)->addHours(3),
        ]);

        $event2 = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(5),
            'end_datetime' => now()->addDays(5)->addHours(3),
        ]);

        $event3 = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->addDays(15),
            'end_datetime' => now()->addDays(15)->addHours(3),
        ]);

        $response = $this->getJson('/api/v1/events');

        $data = $response->json('data');
        $this->assertEquals($event2->id, $data[0]['event_id']);
        $this->assertEquals($event1->id, $data[1]['event_id']);
        $this->assertEquals($event3->id, $data[2]['event_id']);
    }
}
