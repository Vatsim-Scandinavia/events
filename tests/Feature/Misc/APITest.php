<?php

namespace Tests\Feature\Misc;

use App\Models\ApiKey;
use App\Models\Calendar;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class APITest extends TestCase
{
    use WithFaker, RefreshDatabase;
    
    /**
     * Test a system administrator can create an API key with readonly rights.
     */
    public function test_an_admin_can_create_an_api_key_with_readonly()
    {
        $name = $this->faker->sentence(1);

        $this->artisan('create:apikey')
            ->expectsQuestion('Should the API key have edit rights?', 'NO, read only')
            ->expectsQuestion('What should we name the API Key?', $name)
            ->assertExitCode(0);

        // Assert that API key is created in the database with readonly set to true
        $this->assertDatabaseHas('api_keys', [
            'name' => $name,
            'readonly' => 1,
        ]);
    }

    /**
     * Test a system administrator can create an API key with edit rights
     */
    public function test_an_admin_can_create_an_api_key_with_edit_rights()
    {
        $name = $this->faker->sentence(1);

        // Mock user input
        $this->artisan('create:apikey')
            ->expectsQuestion('Should the API key have edit rights?', 1)  // Providing numeric choice
            ->expectsQuestion('What should we name the API Key?', $name)
            ->assertExitCode(0);

        // Assert that the API key was created with edit rights
        $this->assertDatabaseHas('api_keys', [
            'name' => $name,
            'readonly' => 0,
        ]);
    }

    /**
     * Test a API key can be used
     */
    public function test_events_can_be_recieved_from_the_api()
    {
        // Create an API key
        $api = ApiKey::create([
            'id' => '635912f9-c22b-4a63-96e5-14be08a20098',
            'name' => 'Test',
            'readonly' => 0,
            'created_at' => now(),
        ]);

        // Create test event
        $event = Event::factory()->create();

        // Send a GET request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $api->id,
            'Accept' => 'application/json'
        ])->get(route('api.event.index', $event->calendar_id));

        // Assert that the response status is 200 (OK) 
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'calendar_id',
                    'title',
                    'description',
                    'start_date',
                    'end_date',
                    'is_full_day',
                    'recurrence_interval',
                    'recurrence_unit',
                    'recurrence_end_date',
                    'published',
                    'image',
                    'user_id',
                    'area_id',
                    'parent_id',
                    'deleted_at',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        // Assert that the response JSON contains the events
        $response->assertJsonFragment([
            'data' => [
                [
                    'id' => $event->id,
                    'calendar_id' => $event->calendar_id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'is_full_day' => 0,
                    'recurrence_interval' => $event->recurrence_interval,
                    'recurrence_unit' => $event->recurrence_unit,
                    'recurrence_end_date' => $event->recurrence_end,
                    'published' => 0,
                    'image' => $event->image,
                    'user_id' => $event->user_id,
                    'area_id' => $event->area_id,
                    'parent_id' => $event->parent_id,
                    'deleted_at' => $event->deleted_at,
                    'created_at' => $event->created_at->toISOString(),
                    'updated_at' => $event->updated_at->toISOString(),
                ],
            ],
        ]);
    }
}
