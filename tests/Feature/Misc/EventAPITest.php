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
     * Test a API key can be used to retrieve events with instances
     */
    public function test_events_can_be_recieved_from_the_api()
    {
        $api = ApiKey::factory()->create();
        $calendar = Calendar::factory()->create();
        
        // The factory now handles everything!
        $event = Event::factory()->create(['calendar_id' => $calendar->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->get(route('api.event.index', $calendar));

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data'); // verify the event is there
    }

    /**
     * Test an event can be created via the API and generates instances
     */
    public function test_an_event_can_be_created_via_the_api()
    {
        $api = ApiKey::factory()->create(['readonly' => 0]);
        $calendar = Calendar::factory()->create();
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('test.jpg', 1600, 900);

        $eventData = [
            'calendar_id' => $calendar->id,
            'title' => 'Test Event',
            'short_description' => 'Short desc',
            'long_description' => 'Long desc',
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addDay()->addHours(2)->format('Y-m-d H:i'),
            'image' => $image,
            'user' => $user->id,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->post(route('api.event.store'), $eventData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id', 'title', 
                        'instances' => [
                            '*' => ['id', 'start_time', 'end_time']
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('event_instances', [
            'event_id' => $response->json('data.id')
        ]);
    }

    /**
     * Test an event can be deleted via the API and soft deleted
     */
    public function test_an_event_can_be_deleted_via_the_api()
    {
        $api = ApiKey::factory()->create(['readonly' => 0]);
        $event = Event::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$api->id,
            'Accept' => 'application/json',
        ])->delete(route('api.event.destroy', $event));

        $response->assertStatus(200);

        $this->assertSoftDeleted('events', [
            'id' => $event->id
        ]);
        
        $this->assertSoftDeleted('event_instances', [
            'event_id' => $event->id
        ]);
    }
}