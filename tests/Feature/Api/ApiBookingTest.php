<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Staffing;
use App\Models\StaffingBooking;
use App\Models\StaffingPosition;
use App\Models\StaffingSection;
use App\Models\User;
use App\Services\ControlCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiBookingTest extends TestCase
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

        // Mock ControlCenterService so no real HTTP calls are made
        $this->mock(ControlCenterService::class, function ($mock) {
            $mock->shouldReceive('createBooking')->andReturn(42)->byDefault();
            $mock->shouldReceive('deleteBooking')->andReturn(true)->byDefault();
        });
    }

    /**
     * Create a minimal staffing setup and return the message_id + position callsign.
     */
    private function scaffoldStaffing(): array
    {
        $user     = User::factory()->create();
        $calendar = Calendar::factory()->create(['created_by' => $user->id]);
        $event    = Event::factory()->published()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
        ]);
        $occurrence = EventOccurrence::factory()->create([
            'event_id'   => $event->id,
            'start_time' => now()->addDay(),
            'end_time'   => now()->addDay()->addHours(4),
            'status'     => 'scheduled',
        ]);

        $messageId = '987654321012345678';
        $staffing  = Staffing::factory()->create([
            'event_id'           => $event->id,
            'discord_message_id' => $messageId,
        ]);

        $section  = StaffingSection::factory()->create([
            'staffing_id' => $staffing->id,
            'order'       => 1,
        ]);
        $position = StaffingPosition::factory()->create([
            'section_id'  => $section->id,
            'position_id' => 'EKCH_APP',
        ]);

        return [
            'message_id'  => $messageId,
            'position_id' => 'EKCH_APP',
            'position'    => $position,
            'occurrence'  => $occurrence,
            'staffing'    => $staffing,
        ];
    }

    // ------------------------------------------------------------------
    // POST /api/staffings/book
    // ------------------------------------------------------------------

    public function test_book_position_returns_success(): void
    {
        $data = $this->scaffoldStaffing();

        $response = $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1234567,
            'discord_user_id' => '111222333444555666',
            'position'        => $data['position_id'],
            'message_id'      => $data['message_id'],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Position booked successfully']);
    }

    public function test_book_creates_database_record(): void
    {
        $data = $this->scaffoldStaffing();

        $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1234567,
            'discord_user_id' => '111222333444555666',
            'position'        => $data['position_id'],
            'message_id'      => $data['message_id'],
        ]);

        $this->assertDatabaseHas('staffing_bookings', [
            'position_id' => $data['position']->id,
            'vatsim_cid'  => 1234567,
        ]);
    }

    public function test_double_booking_returns_422(): void
    {
        $data = $this->scaffoldStaffing();

        // First booking
        $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1234567,
            'discord_user_id' => '111222333444555666',
            'position'        => $data['position_id'],
            'message_id'      => $data['message_id'],
        ]);

        // Second booking of the same position
        $response = $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 9876543,
            'discord_user_id' => '999888777666555444',
            'position'        => $data['position_id'],
            'message_id'      => $data['message_id'],
        ]);

        $response->assertStatus(422);
    }

    public function test_book_returns_404_for_unknown_staffing(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1234567,
            'discord_user_id' => '111222333444555666',
            'position'        => 'EKCH_APP',
            'message_id'      => '000000000000000000',
        ]);

        $response->assertStatus(404);
    }

    public function test_book_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/staffings/book', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cid', 'discord_user_id', 'position', 'message_id']);
    }

    // ------------------------------------------------------------------
    // POST /api/staffings/unbook
    // ------------------------------------------------------------------

    public function test_unbook_removes_booking(): void
    {
        $data       = $this->scaffoldStaffing();
        $discordUid = '111222333444555666';

        // Create a booking first
        StaffingBooking::factory()->create([
            'position_id'    => $data['position']->id,
            'occurrence_id'  => $data['occurrence']->id,
            'discord_user_id' => $discordUid,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/staffings/unbook', [
            'discord_user_id' => $discordUid,
            'message_id'      => $data['message_id'],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Position unbooked successfully']);

        $this->assertDatabaseMissing('staffing_bookings', [
            'position_id'    => $data['position']->id,
            'discord_user_id' => $discordUid,
        ]);
    }

    public function test_unbook_returns_404_when_no_booking_found(): void
    {
        $data = $this->scaffoldStaffing();

        $response = $this->withToken($this->token)->postJson('/api/staffings/unbook', [
            'discord_user_id' => '999000111222333444',
            'message_id'      => $data['message_id'],
        ]);

        $response->assertStatus(404);
    }

    public function test_unbook_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/staffings/unbook', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discord_user_id', 'message_id']);
    }

    // ------------------------------------------------------------------
    // Duplicate-position guard (issue #8)
    // ------------------------------------------------------------------

    public function test_same_user_cannot_book_two_positions_in_same_occurrence(): void
    {
        $discordUid = '111222333444555666';
        $data       = $this->scaffoldStaffing();

        // Add a second position to the same section
        $section   = $data['position']->section;
        StaffingPosition::factory()->create([
            'section_id'  => $section->id,
            'position_id' => 'EKCH_TWR',
        ]);

        // Book the first position
        $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1234567,
            'discord_user_id' => $discordUid,
            'position'        => $data['position_id'],
            'message_id'      => $data['message_id'],
        ])->assertStatus(200);

        // Attempt to book the second position with the same discord_user_id
        $response = $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1234567,
            'discord_user_id' => $discordUid,
            'position'        => 'EKCH_TWR',
            'message_id'      => $data['message_id'],
        ]);

        $response->assertStatus(422);
    }

    public function test_different_users_can_book_different_positions(): void
    {
        $data = $this->scaffoldStaffing();

        $section = $data['position']->section;
        StaffingPosition::factory()->create([
            'section_id'  => $section->id,
            'position_id' => 'EKCH_TWR',
        ]);

        $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 1111111,
            'discord_user_id' => '111111111111111111',
            'position'        => $data['position_id'],
            'message_id'      => $data['message_id'],
        ])->assertStatus(200);

        $this->withToken($this->token)->postJson('/api/staffings/book', [
            'cid'             => 2222222,
            'discord_user_id' => '222222222222222222',
            'position'        => 'EKCH_TWR',
            'message_id'      => $data['message_id'],
        ])->assertStatus(200);
    }

    public function test_unbook_specific_position_by_callsign(): void
    {
        $data       = $this->scaffoldStaffing();
        $discordUid = '111222333444555666';

        StaffingBooking::factory()->create([
            'position_id'     => $data['position']->id,
            'occurrence_id'   => $data['occurrence']->id,
            'discord_user_id' => $discordUid,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/staffings/unbook', [
            'discord_user_id' => $discordUid,
            'message_id'      => $data['message_id'],
            'position'        => $data['position_id'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('staffing_bookings', ['position_id' => $data['position']->id]);
    }

    public function test_unbook_returns_404_for_unknown_staffing(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/staffings/unbook', [
            'discord_user_id' => '111222333444555666',
            'message_id'      => '000000000000000000',
        ]);

        $response->assertStatus(404);
    }
}
