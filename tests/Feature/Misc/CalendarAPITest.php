<?php

namespace Tests\Feature\Misc;

use App\Models\ApiKey;
use App\Models\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CalendarAPITest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    // Test Calendars can be received from the API
    public function test_calendars_can_be_recieved_from_the_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create();

        $calendar = Calendar::factory()->create();

        // Send a GET request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->get(route('api.calendars.index'));

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'public',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        // Assert that the response JSON contains the calendars
        $response->assertJsonFragment([
            'data' => [
                [
                    'id' => $calendar->id,
                    'name' => $calendar->name,
                    'description' => $calendar->description,
                    'public' => $calendar->public,
                    'created_at' => $calendar->created_at->toISOString(),
                    'updated_at' => $calendar->updated_at->toISOString(),
                ],
            ],
        ]);
    }

    // Test Calendars can be created via API
    public function test_calendars_can_be_created_via_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create([
            'readonly' => 0,
        ]);

        // Mock user input
        $calendarData = [
            'name' => 'Test Calendar',
            'description' => 'This is a test calendar.',
            'public' => 1,
        ];

        // Send a POST request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->post(route('api.calendars.store'), $calendarData);

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'description',
                'public',
                'created_at',
                'updated_at',
            ],
        ]);

        // Assert that the response JSON contains the created calendar
        $response->assertJsonFragment([
            'success' => 'Calendar created',
            'name' => 'Test Calendar',
            'description' => 'This is a test calendar.',
            'public' => 1,
        ]);
    }

    // Test Calendars can be updated via API
    public function test_calendars_can_be_updated_via_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create([
            'readonly' => 0,
        ]);

        // Create a calendar instance
        $calendar = Calendar::factory()->create();

        // Mock user input
        $updatedData = [
            'name' => 'Updated Calendar',
            'description' => 'This is an updated calendar.',
            'public' => 0,
        ];

        // Send a PATCH request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->patch(route('api.calendars.update', $calendar), $updatedData);

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'description',
                'public',
                'created_at',
                'updated_at',
            ],
        ]);

        // Assert that the response JSON contains the updated calendar
        $response->assertJsonFragment([
            'success' => 'Calendar updated',
            'id' => $calendar->id,
            'name' => $updatedData['name'],
            'description' => $updatedData['description'],
            'public' => $updatedData['public'],
        ]);
    }

    // Test Calendars can be deleted via API
    public function test_calendars_can_be_deleted_via_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create([
            'readonly' => 0,
        ]);

        // Create a calendar instance
        $calendar = Calendar::factory()->create();

        // Send a DELETE request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->delete(route('api.calendars.destroy', $calendar));

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the calendar is deleted from the database
        $this->assertDatabaseMissing('calendars', [
            'id' => $calendar->id,
        ]);
    }
}
