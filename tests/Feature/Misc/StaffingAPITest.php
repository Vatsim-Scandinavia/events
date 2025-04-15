<?php

namespace Tests\Feature\Misc;

use App\Models\ApiKey;
use App\Models\Event;
use App\Models\Position;
use App\Models\Staffing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StaffingAPITest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test reset staffing command.
     */
    public function test_reset_staffing_command()
    {
        $now = Carbon::create(2025, 4, 15, 12, 0, 0);
        Carbon::setTestNow($now);

        // Create parent event
        $parentEvent = Event::factory()->create([
            'start_date' => $now->copy()->subDay()->format('Y-m-d H:i'),
            'end_date' => $now->copy()->subDay()->addHours(2)->format('Y-m-d H:i'),
        ]);

        // Attach staffing to parent
        $staffing = Staffing::factory()->create([
            'event_id' => $parentEvent->id,
        ]);

        // Create a child event in the future
        $childEvent = Event::factory()->create([
            'title' => $parentEvent->title,
            'calendar_id' => $parentEvent->calendar_id,
            'parent_id' => $parentEvent->id,
            'start_date' => $now->copy()->addDay()->format('Y-m-d H:i'),
            'end_date' => $now->copy()->addDay()->addHours(2)->format('Y-m-d H:i'),
        ]);

        // Run the command
        $this->artisan('staffing:reset')->assertExitCode(0);

        // Assert staffing points to the child now
        $this->assertDatabaseHas('staffings', [
            'id' => $staffing->id,
            'event_id' => $childEvent->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * Test staffings can be reviecived from the API.
     */
    public function test_staffings_can_be_recieved_from_the_api()
    {
        // Create an API key
        $apiKey = ApiKey::factory()->create();

        // Create a staffing instance with event and position
        $staffing = Staffing::factory()
            ->has(Position::factory())
            ->create();

        $position = $staffing->positions->first();
        $event = $staffing->event;

        // Make the API request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey->id,
            'Accept' => 'application/json',
        ])->get(route('api.staffing.index'));

        // Check status and structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'description',
                        'channel_id',
                        'message_id',
                        'section_1_title',
                        'section_2_title',
                        'section_3_title',
                        'section_4_title',
                        'event_id',
                        'created_at',
                        'updated_at',
                        'positions' => [
                            '*' => [
                                'id',
                                'callsign',
                                'booking_id',
                                'discord_user',
                                'section',
                                'local_booking',
                                'start_time',
                                'end_time',
                                'staffing_id',
                                'created_at',
                                'updated_at',
                            ],
                        ],
                        'event' => [
                            'id',
                            'calendar_id',
                            'title',
                            'short_description',
                            'long_description',
                            'start_date',
                            'end_date',
                            'recurrence_interval',
                            'recurrence_unit',
                            'recurrence_end_date',
                            'published',
                            'image',
                            'user_id',
                            'parent_id',
                            'deleted_at',
                            'created_at',
                            'updated_at',
                        ],
                    ]
                ]
            ]);

        // Assert specific data using assertJsonPath
        $response->assertJsonPath('data.0.id', $staffing->id);
        $response->assertJsonPath('data.0.description', $staffing->description);
        $response->assertJsonPath('data.0.channel_id', $staffing->channel_id);
        $response->assertJsonPath('data.0.message_id', $staffing->message_id);

        // Position details
        $response->assertJsonPath('data.0.positions.0.id', $position->id);
        $response->assertJsonPath('data.0.positions.0.callsign', $position->callsign);
        $response->assertJsonPath('data.0.positions.0.booking_id', $position->booking_id);
        $response->assertJsonPath('data.0.positions.0.discord_user', $position->discord_user);

        // Event details
        $response->assertJsonPath('data.0.event.id', $event->id);
        $response->assertJsonPath('data.0.event.title', $event->title);
        $response->assertJsonPath('data.0.event.short_description', $event->short_description);
        $response->assertJsonPath('data.0.event.start_date', $event->start_date);
        $response->assertJsonPath('data.0.event.end_date', $event->end_date);
    }
}
