<?php

namespace Tests\Feature\Misc;

use App\Models\ApiKey;
use App\Models\Event;
use App\Models\Position;
use App\Models\Staffing;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\EventInstance;

class StaffingAPITest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test reset staffing command.
     */
    public function test_reset_staffing_command()
    {
        // Mock all HTTP requests to prevent real API calls
        Http::fake();

        $now = Carbon::create(2026, 1, 15, 12, 0, 0);
        Carbon::setTestNow($now);

        $event = Event::factory()->create();
        
        // Past instance - completely in the past
        $past = EventInstance::factory()->create([
            'event_id' => $event->id, 
            'start_time' => $now->copy()->subHours(3),
            'end_time' => $now->copy()->subHour()
        ]);
        
        // Future instance - completely in the future  
        $future = EventInstance::factory()->create([
            'event_id' => $event->id, 
            'start_time' => $now->copy()->addHour(),
            'end_time' => $now->copy()->addHours(3)
        ]);

        $staffing = Staffing::factory()->create(['event_instance_id' => $past->id]);

        // Act: Run the reset command
        $this->artisan('staffing:reset')->assertExitCode(0);

        // Assert: Database should be updated to the future instance ID
        $this->assertDatabaseHas('staffings', [
            'id' => $staffing->id,
            'event_instance_id' => $future->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * Test staffings can be received from the API.
     */
    public function test_staffings_can_be_recieved_from_the_api()
    {
        $apiKey = ApiKey::factory()->create();

        // Create a staffing with a linked instance and event
        $staffing = Staffing::factory()
            ->has(Position::factory())
            ->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey->id,
            'Accept' => 'application/json',
        ])->get(route('api.staffing.index'));

        // Verify JSON Structure matches the new 'instance' relationship
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 
                    'description', 
                    'event_instance_id',
                    'instance' => [ // Key is 'instance' because of model method name
                        'id',
                        'start_time',
                        'end_time',
                        'event' => [
                            'id',
                            'title'
                        ]
                    ],
                    'positions'
                ]
            ]
        ]);

        // Updated path assertions to use the 'instance' key
        $response->assertJsonPath('data.0.instance.id', $staffing->event_instance_id);
        
        // Verify the event data is present inside the instance
        $this->assertNotEmpty($response->json('data.0.instance.event'));
    }
}