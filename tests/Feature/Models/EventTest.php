<?php

namespace Tests\Feature\Models;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * A events page can be rendered.
     */
    public function test_events_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the events page
        $response = $this->actingAs($user)->get(route('events.index'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A events page cannot be accessed without correct permissions.
     */
    public function test_events_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the events page
        $response = $this->actingAs($user)->get(route('events.index'));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * A events create page can be rendered.
     */
    public function test_events_create_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the form
        $response = $this->actingAs($user)->get(route('events.create'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A events create page cannot be accessed without correct permissions.
     */
    public function test_events_create_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the form
        $response = $this->actingAs($user)->get(route('events.create'));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * A events edit page can be rendered.
     */
    public function test_events_edit_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create Test Calendar
        Calendar::factory()->create();

        // Create a test event
        $event = Event::factory()->create();

        // Render the edit form
        $response = $this->actingAs($user)->get(route('events.edit', $event));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A events edit page cannot be accessed without correct permissions.
     */
    public function test_events_edit_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Create a test event
        $event = Event::factory()->create();

        // Render the edit form
        $response = $this->actingAs($user)->get(route('events.edit', $event));

        // Check status code
        $response->assertStatus(403);
    }

    public function test_normal_event_can_be_created(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Generate a black test image
        $image = UploadedFile::fake()->image('test_image.jpg', $width = 1600, $height = 900);

        // Post request to create a normal event
        $response = $this->actingAs($user)->post(route('events.store'), [
            'calendar_id' => $calendar->id,
            'event_type' => 0,
            'title' => $this->faker->sentence(1),
            'short_description' => $this->faker->text(280),
            'long_description' => $this->faker->paragraph(),
            'start_date' => now()->addDays(1)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i'),
            'image' => $image,
            'published' => false,
        ]);

        $response->assertRedirect(route('events.show', Event::latest()->first()));
        $response->assertSessionHas('success', function($value) {
            return str_contains($value, "Event '" ) && str_contains($value, "' created!");
        });

        // Check if the file exists
        $event = Event::latest()->first();
        Storage::disk('public')->assertExists('banners/'.$event->image);
    }

    public function test_recurrent_event_can_be_created(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Create test event data
        $startDate = now()->addDays(1)->format('Y-m-d H:i');
        $endDate = now()->addDays(1)->addHours(2)->format('Y-m-d H:i');
        $recurrenceEndDate = now()->addWeeks(5)->format('Y-m-d H:i');

        // Post request to create a recurrent event
        $response = $this->actingAs($user)->post(route('events.store'), [
            'calendar_id' => $calendar->id,
            'event_type' => 1,
            'title' => 'Test Event',
            'short_description' => $this->faker->text(280),
            'long_description' => $this->faker->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'recurrence_interval' => 1,
            'recurrence_unit' => 'week',
            'recurrence_end_date' => $recurrenceEndDate,
            'published' => true,
        ]);

        $response->assertRedirect(route('events.show', Event::latest()->first()));
        // Updated success message
        $response->assertSessionHas('success', "Event 'Test Event' created!");

        // Get the event from the database
        $event = Event::where('title', 'Test Event')->first();

        // Assert the event exists
        $this->assertNotNull($event);

        // Update: Check the instances relationship instead of 'children'
        $instances = $event->instances; 
        $this->assertNotEmpty($instances);

        // Calculate expected number of recurrences
        // Note: The first instance is usually created for the start_date itself
        $expectedDates = collect();
        $currentDate = Carbon::parse($startDate);
        while ($currentDate <= Carbon::parse($recurrenceEndDate)) {
            $expectedDates->push($currentDate->copy());
            $currentDate->addWeek();
        }

        // Assert the number of instances matches the expected count
        $this->assertCount($expectedDates->count(), $instances);

        // Assert the dates of instances match expected dates
        // Using sortBy('start_time') ensures we compare them in order
        $instances = $instances->sortBy('start_time')->values();

        foreach ($instances as $index => $instance) {
            $this->assertEquals(
                Carbon::parse($expectedDates[$index])->format('Y-m-d H:i'), 
                Carbon::parse($instance->start_time)->format('Y-m-d H:i')
            );
            $this->assertEquals(
                Carbon::parse($expectedDates[$index])->addHours(2)->format('Y-m-d H:i'), 
                Carbon::parse($instance->end_time)->format('Y-m-d H:i')
            );
        }
    }

    public function test_event_can_be_updated(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Create a test event (The factory should handle creating the initial instance)
        $event = Event::factory()->create();

        $newTitle = 'Updated Event Title';

        // Patch request to update the event
        $response = $this->actingAs($user)->patch(route('events.update', $event), [
            'calendar_id' => $calendar->id,
            'event_type' => 0, // Added boolean (0 for single)
            'title' => $newTitle,
            'short_description' => $this->faker->text(280),
            'long_description' => $this->faker->paragraph(),
            'start_date' => now()->addDays(2)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'recurrence_end_date' => null,
        ]);

        // Check redirect and updated success message
        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success', 'Event and/or series updated!');

        // Refresh model and assert changes
        $this->assertEquals($newTitle, $event->refresh()->title);
    }

    public function test_event_can_be_deleted(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test event
        $event = Event::factory()->create();

        // Delete a test calendar
        $response = $this->actingAs($user)->delete(route('events.destroy', $event));

        // Check redirect and session is correct
        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success', 'Event removed.');

        // Check if the event is deleted using soft delete logic
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'deleted_at' => now(),
        ]);

    }

    protected function getUser()
    {
        $user = User::factory()->create();
        $user->groups()->attach(1);

        return $user;
    }

    protected function getImage()
    {
        // Create a 16:9 image
        // Remember `install-php-extensions gd` if you try to run this locally, otherwise this test will fail.
        $width = 1600;
        $height = 900;
        $image = imagecreate($width, $height);
        $background = imagecolorallocate($image, 0, 0, 0); // black background
        $imagePath = storage_path('framework/testing/test_image.jpg');
        imagejpeg($image, $imagePath);
        imagedestroy($image);

        return $imagePath;
    }
}
