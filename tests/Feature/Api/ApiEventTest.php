<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEventTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $plain = bin2hex(random_bytes(32));
        ApiKey::create([
            'name'      => 'test',
            'key'       => hash('sha256', $plain),
            'read_only' => false,
        ]);
        $this->token = $plain;
    }

    private function api(): \Illuminate\Testing\TestResponse
    {
        // helper placeholder — actual calls use withToken() inline
        return $this->withToken($this->token)->getJson('/api/events');
    }

    // ------------------------------------------------------------------
    // GET /api/events
    // ------------------------------------------------------------------

    public function test_get_events_returns_array(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_get_events_includes_published_upcoming_event(): void
    {
        $user     = User::factory()->create();
        $calendar = Calendar::factory()->create(['created_by' => $user->id]);
        $event    = Event::factory()->published()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
        ]);
        EventOccurrence::factory()->create([
            'event_id'   => $event->id,
            'start_time' => now()->addDay(),
            'end_time'   => now()->addDay()->addHours(2),
            'status'     => 'scheduled',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/events');

        $response->assertStatus(200);
        $data = $response->json();
        $ids = collect($data)->pluck('id');
        $this->assertContains($event->id, $ids->toArray());
    }

    public function test_get_events_returns_has_staffing_field(): void
    {
        $user     = User::factory()->create();
        $calendar = Calendar::factory()->create(['created_by' => $user->id]);
        $event    = Event::factory()->published()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
        ]);
        EventOccurrence::factory()->create([
            'event_id'   => $event->id,
            'start_time' => now()->addDay(),
            'end_time'   => now()->addDay()->addHours(2),
            'status'     => 'scheduled',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/events');

        $data = $response->json();
        $item = collect($data)->firstWhere('id', $event->id);
        $this->assertArrayHasKey('has_staffing', $item);
        $this->assertFalse($item['has_staffing']);
    }

    // ------------------------------------------------------------------
    // GET /api/events/{id}
    // ------------------------------------------------------------------

    public function test_get_single_event_returns_correct_id(): void
    {
        $user     = User::factory()->create();
        $calendar = Calendar::factory()->create(['created_by' => $user->id]);
        $event    = Event::factory()->published()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
        ]);

        $response = $this->withToken($this->token)->getJson("/api/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $event->id]);
    }

    public function test_get_single_event_returns_404_for_missing_id(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/events/9999999');

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // GET /api/events/{id}/staffing
    // ------------------------------------------------------------------

    public function test_get_event_staffing_returns_404_when_no_staffing(): void
    {
        $user     = User::factory()->create();
        $calendar = Calendar::factory()->create(['created_by' => $user->id]);
        $event    = Event::factory()->published()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
        ]);

        $response = $this->withToken($this->token)->getJson("/api/events/{$event->id}/staffing");

        $response->assertStatus(404);
    }

    public function test_get_event_staffing_returns_staffing_data(): void
    {
        $user     = User::factory()->create();
        $calendar = Calendar::factory()->create(['created_by' => $user->id]);
        $event    = Event::factory()->published()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
        ]);
        EventOccurrence::factory()->create([
            'event_id'   => $event->id,
            'start_time' => now()->addDay(),
            'end_time'   => now()->addDay()->addHours(2),
            'status'     => 'scheduled',
        ]);
        \App\Models\Staffing::factory()->create(['event_id' => $event->id]);

        $response = $this->withToken($this->token)->getJson("/api/events/{$event->id}/staffing");

        $response->assertStatus(200)
            ->assertJsonFragment(['event_id' => $event->id]);
    }
}
