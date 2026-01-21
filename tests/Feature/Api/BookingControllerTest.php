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
use App\Jobs\UpdateDiscordStaffingMessage;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase, WithApiAuthentication;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // Fake Discord webhooks but allow Control Center API calls
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
        ]);
        $this->setUpApiKeys();
    }

    #[Test]
    public function it_can_book_a_position_successfully()
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
            'https://cc.vatsim-scandinavia.org/api/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
            '*/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
        ]);

        $event = $this->createEventWithStaffing();
        
        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'ESSA_TWR',
            'message_id' => $event->discord_staffing_message_id,
            'section' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Position booked successfully']);

        $position = StaffingPosition::where('position_id', 'ESSA_TWR')->first();
        $this->assertEquals(1234567, $position->vatsim_cid);
        $this->assertEquals('987654321', $position->discord_user_id);
        $this->assertEquals(12345, $position->control_center_booking_id);

        Queue::assertPushed(UpdateDiscordStaffingMessage::class);
    }

    #[Test]
    public function it_returns_404_when_event_not_found()
    {
        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'ESSA_TWR',
            'message_id' => 'invalid_message_id',
            'section' => 1,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Staffing not found']);
    }

    #[Test]
    public function it_returns_404_when_position_not_found()
    {
        $event = $this->createEventWithStaffing();

        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'INVALID_POS',
            'message_id' => $event->discord_staffing_message_id,
            'section' => 1,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Position not found']);
    }

    #[Test]
    public function it_returns_422_when_position_already_booked()
    {
        $event = $this->createEventWithStaffing();
        $position = StaffingPosition::where('position_id', 'ESSA_TWR')->first();
        $position->update([
            'vatsim_cid' => 9999999,
            'discord_user_id' => '111111111',
        ]);

        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'ESSA_TWR',
            'message_id' => $event->discord_staffing_message_id,
            'section' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Position already booked']);
    }

    #[Test]
    public function it_validates_required_fields_for_booking()
    {
        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cid', 'discord_user_id', 'position', 'message_id']);
    }

    #[Test]
    public function it_can_unbook_a_position_successfully()
    {
        Http::fake([
            '*/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
        ]);

        $event = $this->createEventWithStaffing();
        $position = StaffingPosition::where('position_id', 'ESSA_TWR')->first();
        $position->update([
            'vatsim_cid' => 1234567,
            'discord_user_id' => '987654321',
            'control_center_booking_id' => 12345,
        ]);

        $response = $this->withWriteApiKey()->deleteJson('/api/v1/staffing', [
            'discord_user_id' => '987654321',
            'message_id' => $event->discord_staffing_message_id,
            'position' => 'ESSA_TWR',
            'section' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Position unbooked successfully']);

        $position->refresh();
        $this->assertNull($position->vatsim_cid);
        $this->assertNull($position->discord_user_id);
        $this->assertNull($position->control_center_booking_id);

        Queue::assertPushed(UpdateDiscordStaffingMessage::class);
    }

    #[Test]
    public function it_can_unbook_all_positions_for_user_when_no_position_specified()
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
        ]);

        $event = $this->createEventWithStaffing();
        
        // Book multiple positions for same user
        $positions = StaffingPosition::whereIn('position_id', ['ESSA_TWR', 'ESSA_APP'])->get();
        foreach ($positions as $position) {
            $position->update([
                'vatsim_cid' => 1234567,
                'discord_user_id' => '987654321',
                'control_center_booking_id' => 12345,
            ]);
        }

        $response = $this->withWriteApiKey()->deleteJson('/api/v1/staffing', [
            'discord_user_id' => '987654321',
            'message_id' => $event->discord_staffing_message_id,
        ]);

        $response->assertStatus(200);

        foreach ($positions as $position) {
            $position->refresh();
            $this->assertNull($position->vatsim_cid);
            $this->assertNull($position->discord_user_id);
        }
    }

    #[Test]
    public function it_handles_control_center_api_failure_gracefully()
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
            'https://cc.vatsim-scandinavia.org/api/bookings/create' => Http::response(['error' => 'API Error'], 500),
            '*/bookings/create' => Http::response(['error' => 'API Error'], 500),
        ]);

        $event = $this->createEventWithStaffing();

        $response = $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'ESSA_TWR',
            'message_id' => $event->discord_staffing_message_id,
            'section' => 1,
        ]);

        $response->assertStatus(200); // Still succeeds locally

        $position = StaffingPosition::where('position_id', 'ESSA_TWR')->first();
        $this->assertEquals(1234567, $position->vatsim_cid);
        $this->assertNull($position->control_center_booking_id); // But no booking ID
    }

    #[Test]
    public function it_calculates_booking_times_from_position_times()
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
            'https://cc.vatsim-scandinavia.org/api/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
            '*/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
        ]);

        $event = $this->createEventWithStaffing();
        $position = StaffingPosition::where('position_id', 'ESSA_TWR')->first();
        $position->update([
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
        ]);

        $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'ESSA_TWR',
            'message_id' => $event->discord_staffing_message_id,
            'section' => 1,
        ]);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/bookings/create')) {
                return false;
            }
            $data = $request->data();
            return isset($data['start_at']) && isset($data['end_at'])
                && $data['start_at'] === '18:00' 
                && $data['end_at'] === '20:00';
        });
    }

    #[Test]
    public function it_handles_recurring_events_correctly()
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 200),
            'https://cc.vatsim-scandinavia.org/api/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
            '*/bookings/create' => Http::response(['booking' => ['id' => 12345]], 200),
        ]);

        $event = $this->createRecurringEventWithStaffing();

        $this->withWriteApiKey()->postJson('/api/v1/staffing', [
            'cid' => 1234567,
            'discord_user_id' => '987654321',
            'position' => 'ESSA_TWR',
            'message_id' => $event->discord_staffing_message_id,
            'section' => 1,
        ]);

        Http::assertSent(function ($request) use ($event) {
            if (!str_contains($request->url(), '/bookings/create')) {
                return false;
            }
            $data = $request->data();
            if (!isset($data['date'])) {
                return false;
            }
            // Should use next occurrence date, not the original event date
            $sentDate = \Carbon\Carbon::createFromFormat('d/m/Y', $data['date']);
            return $sentDate->isAfter($event->start_datetime);
        });
    }

    protected function createEventWithStaffing(): Event
    {
        $calendar = Calendar::factory()->create();
        
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'discord_staffing_message_id' => 'test_message_123',
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(3),
        ]);

        $staffing = Staffing::factory()->create([
            'event_id' => $event->id,
            'order' => 1,
        ]);

        StaffingPosition::factory()->create([
            'staffing_id' => $staffing->id,
            'position_id' => 'ESSA_TWR',
            'vatsim_cid' => null,
            'discord_user_id' => null,
        ]);

        StaffingPosition::factory()->create([
            'staffing_id' => $staffing->id,
            'position_id' => 'ESSA_APP',
            'vatsim_cid' => null,
            'discord_user_id' => null,
        ]);

        return $event;
    }

    protected function createRecurringEventWithStaffing(): Event
    {
        $calendar = Calendar::factory()->create();
        
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'discord_staffing_message_id' => 'recurring_message_123',
            'start_datetime' => now()->subDays(30),
            'end_datetime' => now()->subDays(30)->addHours(3),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=FR',
        ]);

        $staffing = Staffing::factory()->create([
            'event_id' => $event->id,
            'order' => 1,
        ]);

        StaffingPosition::factory()->create([
            'staffing_id' => $staffing->id,
            'position_id' => 'ESSA_TWR',
        ]);

        return $event;
    }
}
