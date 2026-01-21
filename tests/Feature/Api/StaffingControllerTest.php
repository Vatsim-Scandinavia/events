<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\Event;
use App\Models\Staffing;
use App\Models\StaffingPosition;
use App\Models\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class StaffingControllerTest extends TestCase
{
    use RefreshDatabase, WithApiAuthentication;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
        ]);
        $this->setUpApiKeys();
    }

    #[Test]
    public function it_can_get_all_staffings()
    {
        $calendar = Calendar::factory()->create();
        $event1 = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'discord_staffing_channel_id' => 'channel_123',
            'discord_staffing_message_id' => 'message_123',
        ]);
        $event2 = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'discord_staffing_channel_id' => 'channel_456',
            'discord_staffing_message_id' => 'message_456',
        ]);

        Staffing::factory()->create(['event_id' => $event1->id]);
        Staffing::factory()->create(['event_id' => $event2->id]);

        $response = $this->withReadApiKey()->getJson('/api/v1/staffings');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_get_staffing_by_message_id()
    {
        $event = $this->createEventWithStaffing();

        $response = $this->withReadApiKey()->getJson('/api/v1/staffings/message?message_id=' . $event->discord_staffing_message_id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'event_id',
                    'title',
                    'discord',
                    'staffing',
                ]
            ]);
    }

    #[Test]
    public function it_returns_400_when_message_id_missing()
    {
        $response = $this->withReadApiKey()->getJson('/api/v1/staffings/message');

        $response->assertStatus(400)
            ->assertJson(['error' => 'message_id parameter required']);
    }

    #[Test]
    public function it_returns_404_when_message_id_not_found()
    {
        $response = $this->withReadApiKey()->getJson('/api/v1/staffings/message?message_id=invalid');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Staffing not found']);
    }

    #[Test]
    public function it_can_get_staffing_by_section_id()
    {
        $event = $this->createEventWithStaffing();
        $staffing = $event->staffings->first();

        $response = $this->withReadApiKey()->getJson("/api/v1/staffings/{$staffing->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'event_id',
                    'title',
                    'discord',
                    'staffing',
                ]
            ]);
    }

    #[Test]
    public function it_can_get_event_staffing_by_event_id()
    {
        $event = $this->createEventWithStaffing();

        $response = $this->withReadApiKey()->getJson("/api/v1/events/{$event->id}/staffing");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'event_id',
                    'title',
                    'discord',
                    'staffing',
                ]
            ]);
    }

    #[Test]
    public function it_can_update_staffing_message_id()
    {
        $event = $this->createEventWithStaffing();
        $staffing = $event->staffings->first();

        $response = $this->withWriteApiKey()->patchJson("/api/v1/staffings/{$staffing->id}/update", [
            'message_id' => 'new_message_123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Staffing updated successfully']);

        $event->refresh();
        $this->assertEquals('new_message_123', $event->discord_staffing_message_id);
    }

    #[Test]
    public function it_validates_message_id_on_update()
    {
        $event = $this->createEventWithStaffing();
        $staffing = $event->staffings->first();

        $response = $this->withWriteApiKey()->patchJson("/api/v1/staffings/{$staffing->id}/update", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message_id']);
    }

    #[Test]
    public function it_can_setup_staffing()
    {
        $event = $this->createEventWithStaffing();
        $staffing = $event->staffings->first();

        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing/setup', [
            'id' => $staffing->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Staffing setup initiated']);
    }

    #[Test]
    public function it_validates_staffing_id_on_setup()
    {
        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing/setup', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    #[Test]
    public function it_returns_400_when_no_discord_channel_on_setup()
    {
        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'discord_staffing_channel_id' => null,
        ]);

        $staffing = Staffing::factory()->create(['event_id' => $event->id]);

        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing/setup', [
            'id' => $staffing->id,
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'No Discord channel configured for this event']);
    }

    #[Test]
    public function it_can_reset_all_bookings()
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
        ]);

        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->subDays(30),
            'end_datetime' => now()->subDays(30)->addHours(3),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=FR',
            'discord_staffing_channel_id' => 'channel_123',
            'discord_staffing_message_id' => 'message_123',
        ]);

        $staffing = Staffing::factory()->create(['event_id' => $event->id]);
        
        // Book some positions
        $positions = StaffingPosition::factory()->count(2)->create([
            'staffing_id' => $staffing->id,
            'vatsim_cid' => 1234567,
            'discord_user_id' => '987654321',
            'control_center_booking_id' => 12345,
        ]);

        $response = $this->withWriteApiKey()->postJson("/api/v1/staffings/{$staffing->id}/reset");

        $response->assertStatus(200)
            ->assertJson(['message' => 'All staffing positions have been reset successfully']);

        foreach ($positions as $position) {
            $position->refresh();
            $this->assertNull($position->vatsim_cid);
            $this->assertNull($position->discord_user_id);
            $this->assertNull($position->control_center_booking_id);
        }
    }

    #[Test]
    public function it_returns_400_when_resetting_non_recurring_event()
    {
        $event = $this->createEventWithStaffing();
        $staffing = $event->staffings->first();

        $response = $this->withWriteApiKey()->postJson("/api/v1/staffings/{$staffing->id}/reset");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Staffing reset is only available for recurring events']);
    }

    #[Test]
    public function it_handles_recurring_events_in_staffing_response()
    {
        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'start_datetime' => now()->subDays(30),
            'end_datetime' => now()->subDays(30)->addHours(3),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=FR',
        ]);

        $staffing = Staffing::factory()->create(['event_id' => $event->id]);

        $response = $this->withReadApiKey()->getJson("/api/v1/events/{$event->id}/staffing");

        $response->assertStatus(200);
        
        // Should include event data
        $data = $response->json('data');
        $this->assertArrayHasKey('event_id', $data);
        $this->assertArrayHasKey('title', $data);
    }

    protected function createEventWithStaffing(): Event
    {
        $calendar = Calendar::factory()->create();
        
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'discord_staffing_message_id' => 'test_message_123',
            'discord_staffing_channel_id' => 'channel_123',
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);

        $staffing = Staffing::factory()->create([
            'event_id' => $event->id,
            'order' => 1,
        ]);

        StaffingPosition::factory()->count(3)->create([
            'staffing_id' => $staffing->id,
        ]);

        return $event;
    }
}
