<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Staffing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiStaffingTest extends TestCase
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

    private function createEventWithStaffing(string $messageId = null): array
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
        $staffing = Staffing::factory()->create([
            'event_id'           => $event->id,
            'discord_message_id' => $messageId,
        ]);

        return [$event, $staffing];
    }

    // ------------------------------------------------------------------
    // GET /api/staffings
    // ------------------------------------------------------------------

    public function test_get_all_staffings_returns_data_key(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/staffings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_get_all_staffings_excludes_staffings_without_message_id(): void
    {
        $this->createEventWithStaffing(messageId: null);

        $response = $this->withToken($this->token)->getJson('/api/staffings');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    // ------------------------------------------------------------------
    // GET /api/staffings?message_id=xxx
    // ------------------------------------------------------------------

    public function test_get_staffing_by_message_id_returns_404_when_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/staffings?message_id=999999999999999999');

        $response->assertStatus(404);
    }

    public function test_get_staffings_without_message_id_param_returns_list(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/staffings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ------------------------------------------------------------------
    // GET /api/staffings/{id}
    // ------------------------------------------------------------------

    public function test_get_staffing_by_id_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/staffings/9999999');

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // PATCH /api/staffings/{id}/update
    // ------------------------------------------------------------------

    public function test_update_staffing_message_id_stores_value(): void
    {
        [, $staffing] = $this->createEventWithStaffing();

        $newMessageId = '123456789012345678';

        $response = $this->withToken($this->token)
            ->patchJson("/api/staffings/{$staffing->id}/update", [
                'message_id' => $newMessageId,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Staffing updated successfully']);

        $this->assertDatabaseHas('staffings', [
            'id'                 => $staffing->id,
            'discord_message_id' => $newMessageId,
        ]);
    }

    public function test_update_staffing_requires_message_id(): void
    {
        [, $staffing] = $this->createEventWithStaffing();

        $response = $this->withToken($this->token)
            ->patchJson("/api/staffings/{$staffing->id}/update", []);

        $response->assertStatus(422);
    }

    public function test_update_staffing_returns_404_for_missing_staffing(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/staffings/9999999/update', ['message_id' => '123']);

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /api/staffings/{id}/reset
    // ------------------------------------------------------------------

    public function test_reset_staffing_fails_for_non_recurring_event(): void
    {
        [, $staffing] = $this->createEventWithStaffing();

        $response = $this->withToken($this->token)
            ->postJson("/api/staffings/{$staffing->id}/reset");

        $response->assertStatus(400);
    }
}
