<?php

namespace Tests\Feature\Misc;

use App\Models\ApiKey;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EventAPITest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    /**
     * Test a API key can be used
     */
    public function test_events_can_be_recieved_from_the_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create();

        // Create test event
        $image = UploadedFile::fake()->image('test_image.jpg', $width = 1600, $height = 900);
        $event = Event::factory()->create([
            'image' => $image,
        ]);

        // Send a GET request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->get(route('api.event.index', $event->calendar));

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'data' => [
                '*' => [
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
            ],
        ]);

        // Assert that the response JSON contains the events
        $response->assertJsonFragment([
            'data' => [
                [
                    'id' => $event->id,
                    'calendar_id' => $event->calendar_id,
                    'title' => $event->title,
                    'short_description' => $event->short_description,
                    'long_description' => $event->long_description,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'recurrence_interval' => $event->recurrence_interval,
                    'recurrence_unit' => $event->recurrence_unit,
                    'recurrence_end_date' => $event->recurrence_end,
                    'published' => 0,
                    'image' => $event->image,
                    'user_id' => $event->user_id,
                    'parent_id' => $event->parent_id,
                    'deleted_at' => $event->deleted_at,
                    'created_at' => $event->created_at->toISOString(),
                    'updated_at' => $event->updated_at->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Test an event can be created via the API
     */
    public function test_an_event_can_be_created_via_the_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create([
            'readonly' => 0,
        ]);

        // Create test event
        $image = UploadedFile::fake()->image('test_image.jpg', $width = 1600, $height = 900);

        $calendar = Calendar::factory()->create();

        $user = User::factory()->create();

        $eventData = [
            'calendar_id' => $calendar->id,
            'title' => 'Test Event',
            'short_description' => 'This is a test event',
            'long_description' => 'This is a longer description of the test event',
            'start_date' => now()->format('Y-m-d H:i'),
            'end_date' => now()->addDays(2)->format('Y-m-d H:i'),
            'event_type' => 1,
            'recurrence_interval' => 1,
            'recurrence_unit' => 'day',
            'recurrence_end_date' => now()->addDays(10)->format('Y-m-d H:i'),
            'image' => $image,
            'user' => $user->id,
        ];

        // Send a POST request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->post(route('api.event.store'), $eventData);

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'success',
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
                'image',
                'user_id',
                "user" => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'last_login',
                    'access_token',
                    'refresh_token',
                    'token_expires',
                    'created_at',
                    'updated_at'
                ]
            ],
        ]);

        // Assert that the response JSON contains the created event
        $response->assertJsonFragment([
            'success' => 'Event created',
            'calendar_id' => $eventData['calendar_id'],
            'title' => $eventData['title'],
            'short_description' => $eventData['short_description'],
            'long_description' => $eventData['long_description'],
            'start_date' => $eventData['start_date'],
            'end_date' => $eventData['end_date'],
            'recurrence_interval' => $eventData['recurrence_interval'],
            'recurrence_unit' => $eventData['recurrence_unit'],
            'recurrence_end_date' => $eventData['recurrence_end_date'],
            'user_id' => $eventData['user'],
        ]);
    }

    /**
     * Test an event can be updated via the API
     */
    public function test_an_event_can_be_updated_via_the_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create([
            'readonly' => 0,
        ]);

        // Create test event
        $image = UploadedFile::fake()->image('test_image.jpg', $width = 1600, $height = 900);
        $event = Event::factory()->create([
            'image' => $image,
            'user_id' => User::factory()->create()->id,
        ]);

        // Create updated event data
        $updatedEventData = [
            'calendar_id' => $event->calendar_id,
            'title' => 'Updated Test Event',
            'short_description' => 'This is an updated test event',
            'long_description' => 'This is a longer description of the updated test event',
            'start_date' => now()->format('Y-m-d H:i'),
            'end_date' => now()->addDays(3)->format('Y-m-d H:i'),
            'recurrence_interval' => 2,
            'recurrence_unit' => 'week',
            'recurrence_end_date' => now()->addDays(20)->format('Y-m-d H:i'),
            'image' => $image,
            'user' => $event->user_id,
        ];

        // Send a PATCH request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->patch(route('api.event.update', $event), $updatedEventData);

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'success',
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
                'user' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'last_login',
                    'access_token',
                    'refresh_token',
                    'token_expires',
                    'created_at',
                    'updated_at'
                ]
            ],
        ]);

        // Assert that the response JSON contains the updated event
        $response->assertJsonFragment([
            'success' => 'Event updated',
            'id' => $event->id,
            'calendar_id' => $event->calendar_id,
            'title' => $updatedEventData['title'],
            'short_description' => $updatedEventData['short_description'],
            'long_description' => $updatedEventData['long_description'],
            'start_date' => $updatedEventData['start_date'],
            'end_date' => $updatedEventData['end_date'],
            'recurrence_interval' => $updatedEventData['recurrence_interval'],
            'recurrence_unit' => $updatedEventData['recurrence_unit'],
            'recurrence_end_date' => $updatedEventData['recurrence_end_date'],
            'user_id' => $event->user_id,
            'parent_id' => $event->parent_id,
            'deleted_at' => $event->deleted_at,
            'created_at' => $event->created_at->toISOString(),
        ]);
    }

    /**
     * Test an event can be deleted via the API
     */
    public function test_an_event_can_be_deleted_via_the_api()
    {
        // Create an API key
        $api = ApiKey::factory()->create([
            'readonly' => 0,
        ]);

        // Create test event
        $image = UploadedFile::fake()->image('test_image.jpg', $width = 1600, $height = 900);
        $event = Event::factory()->create([
            'image' => $image,
        ]);

        // Send a DELETE request to the API endpoint with the bearer token in the headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->delete(route('api.event.destroy', $event));

        // Assert that the response status is 200 (OK)
        $response->assertStatus(200);

        // Assert that the response JSON structure contains the expected keys
        $response->assertJsonStructure([
            'success',
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
        ]);

        // Assert that the response JSON contains the deleted event
        $response->assertJsonFragment([
            'success' => 'Event deleted',
            'event' => [
                'id' => $event->id,
                'calendar_id' => $event->calendar_id,
                'title' => $event->title,
                'short_description' => $event->short_description,
                'long_description' => $event->long_description,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'recurrence_interval' => $event->recurrence_interval,
                'recurrence_unit' => $event->recurrence_unit,
                'recurrence_end_date' => $event->recurrence_end_date,
                'published' => $event->published,
                'image' => $event->image,
                'user_id' => $event->user_id,
                'parent_id' => $event->parent_id,
                'deleted_at' => $event->deleted_at,
                'created_at' => $event->created_at->toISOString(),
                'updated_at' => $event->updated_at->toISOString(),
            ]
        ]);

        // Assert that the event has been deleted
        $this->assertNotNull($event->fresh()->deleted_at);
    }

}
