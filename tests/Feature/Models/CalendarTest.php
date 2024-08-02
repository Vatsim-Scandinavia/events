<?php

namespace Tests\Feature\Models;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * A calendars page can be rendered.
     */
    public function test_calendars_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the calendars page
        $response = $this->actingAs($user)->get(route('calendars.index'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A calendars page cannot be accessed without correct permissions.
     */
    public function test_calendars_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the calendar
        $response = $this->actingAs($user)->get(route('calendars.index'));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * A calendars create page can be rendered.
     */
    public function test_calendars_create_page_can_be_rendered(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the form
        $response = $this->actingAs($user)->get(route('calendars.create'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A calendars create page cannot be accessed without correct permissions.
     */
    public function test_calendars_create_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the form
        $response = $this->actingAs($user)->get(route('calendars.create'));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * A calendars edit page can be rendered.
     */
    public function test_calendars_edit_page_can_be_rendered(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();


        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Render the edit form
        $response = $this->actingAs($user)->get(route('calendars.edit', $calendar));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A calendars edit page cannot be accessed without correct permissions.
     */
    public function test_calendars_edit_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Render the edit form
        $response = $this->actingAs($user)->get(route('calendars.edit', $calendar));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * A calendar page can be rendered.
     */
    public function test_calendar_page_can_be_rendered(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();
        
        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Render the calendar
        $response = $this->actingAs($user)->get(route('calendar', $calendar));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A calendar page cannot be accessed when not public
     */
    public function test_calendar_page_cannot_be_rendered_when_not_public(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Render the calendar
        $response = $this->actingAs($user)->get(route('calendar', $calendar));

        // Check status code
        $response->assertStatus(403);
    }

    public function test_calendar_can_be_created(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Post request to create a new calendar
        $response = $this->actingAs($user)->post(route('calendars.store'), [
            'name' => $this->faker->sentence(1),
            'description' => $this->faker->paragraph(),
            'public' => rand(0, 1),
        ]);

        // Check redirect and session is correct
        $response->assertRedirect(route('calendars.index'));
        $response->assertSessionHas('success', 'Calendar has been created successfully');
    }

    public function test_calendar_can_be_updated(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Patch request to update the calendar
        $response = $this->actingAs($user)->patch(route('calendars.update', $calendar), [
            'name' => $this->faker->sentence(1),
            'description' => $this->faker->paragraph(),
            'public' => rand(0, 1),
        ]);

        // Check redirect and session is correct
        $response->assertRedirect(route('calendars.index'));
        $response->assertSessionHas('success', 'Calendar updated successfully');
    }

    public function test_calendar_can_be_deleted(): void 
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a test calendar
        $calendar = Calendar::factory()->create();

        // Delete a test calendar
        $response = $this->actingAs($user)->delete(route('calendars.destroy', $calendar));

        // Check redirect and session is correct
        $response->assertRedirect(route('calendars.index'));
        $response->assertSessionHas('success', 'Successfully deleted calendar');
    }

    protected function getUser()
    {
        $user = User::factory()->create();
        $user->groups()->attach(1);

        return $user;
    }
}
