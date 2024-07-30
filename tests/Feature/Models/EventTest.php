<?php

namespace Tests\Feature\Models;

use App\Models\Area;
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
    use WithFaker, RefreshDatabase;

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

    public function test_normal_event_can_be_created() : void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        $area = Area::find(rand(1,5));

        Storage::fake('public');

        $imagePath = $this->getImage();

        $image = new \Illuminate\Http\UploadedFile($imagePath, 'test_image.jpg', 'image/jpeg', null, true);

        // Post request to create a normal event
        $response = $this->actingAs($user)->post(route('events.store'), [
            'area' => $area->id,
            'calendar_id' => $calendar->id,
            'title' => $this->faker->sentence(1),
            'description' => $this->faker->paragraph(),
            'start_date' => now()->addDays(1)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i'),
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'recurrence_end_date' => null,
            'image' => $image,
        ]);

        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success', 'Event created successfully.');

        // Retrieve the event to get the stored image path
        $event = Event::latest()->first();
        
        Storage::disk('public')->assertExists('images/' . $event->image);
    }

    public function test_recurrent_event_can_be_created() : void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        $area = Area::find(rand(1,5));

        // Create test event data
        $startDate = now()->addDays(1)->format('Y-m-d H:i');
        $endDate = now()->addDays(1)->addHours(2)->format('Y-m-d H:i');
        $recurrenceEndDate = now()->addWeeks(5)->format('Y-m-d H:i');

        // Post request to create a normal event
        $response = $this->actingAs($user)->post(route('events.store'), [
            'area' => $area->id,
            'calendar_id' => $calendar->id,
            'title' => 'Test Event',
            'description' => $this->faker->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'recurrence_interval' => 1,
            'recurrence_unit' => 'week',
            'recurrence_end_date' => $recurrenceEndDate,
        ]);

        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success', 'Event created successfully.');

        // Get the event from the database
        $event = Event::where('title', 'Test Event')->first();

        // Assert the event exists
        $this->assertNotNull($event);

        // Assert the event has recurrences
        $recurrences = $event->children;
        $this->assertNotEmpty($recurrences);

        // Calculate expected number of recurrences
        $expectedRecurrences = collect();
        $currentDate = Carbon::parse($startDate)->addWeek();
        while ($currentDate <= Carbon::parse($recurrenceEndDate)) {
            $expectedRecurrences->push($currentDate->copy());
            $currentDate->addWeek();
        }

        // Assert the number of recurrences matches the expected number
        $this->assertCount($expectedRecurrences->count(), $recurrences);

        // Assert the dates of recurrences match expected dates
        foreach ($recurrences as $index => $recurrence) {
            $this->assertEquals(Carbon::parse($expectedRecurrences[$index])->format('Y-m-d H:i'), Carbon::parse($recurrence->start_date)->format('Y-m-d H:i'));
            $this->assertEquals(Carbon::parse($expectedRecurrences[$index])->addHours(2)->format('Y-m-d H:i'), Carbon::parse($recurrence->end_date)->format('Y-m-d H:i'));
        }
    }

    public function test_event_can_be_updated(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        $area = Area::find(rand(1,5));

        // Create a test event
        $event = Event::factory()->create();

        // Patch request to update the calendar
        $response = $this->actingAs($user)->patch(route('events.update', $event), [
            'area' => $area->id,
            'calendar_id' => $calendar->id,
            'title' => $this->faker->sentence(1),
            'description' => $this->faker->paragraph(),
            'start_date' => now()->addDays(1)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i'),
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'recurrence_end_date' => null,
        ]);

        // Check redirect and session is correct
        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success', 'Event updated successfully.');
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
        $response->assertSessionHas('success', 'Event deleted successfully');
    }

    protected function getUser()
    {
        $area = Area::find(rand(1,5));
        $user = User::factory()->create();
        $user->groups()->attach(1, ['area_id' => $area->id]);

        return $user;
    }

    protected function getImage()
    {
        // Create a 16:9 image
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
